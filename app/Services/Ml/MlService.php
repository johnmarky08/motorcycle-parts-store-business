<?php

namespace App\Services\Ml;

use App\Models\Dataset;
use App\Models\MlModel;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Bridge between Laravel and the Python machine-learning package in /ml.
 *
 * Security: the command is always an argument array (never a shell string), user input is never part of
 * a path or argument, JSON goes through stdin, Python can only touch files inside storage/app/ml, and a
 * saved model is only loaded after its SHA-256 matches the hash stored in the database.
 */
class MlService
{
    public function baseDir(): string
    {
        return storage_path('app/ml');
    }

    /**
     * Interpreter used for the analysis. Order: ML_PYTHON, project .venv, then "python3"/"python".
     * On Windows the windowless "pythonw.exe" next to python.exe is preferred so that no console
     * window ever appears; python.exe is the automatic fallback (see run()).
     */
    public function pythonBinary(bool $windowless = true): string
    {
        $configured = config('store.ml.python');
        $candidates = is_string($configured) && $configured !== '' ? [$configured] : [
            base_path('.venv/Scripts/python.exe'), base_path('.venv/bin/python'),
            base_path('venv/Scripts/python.exe'), base_path('venv/bin/python'),
        ];

        $binary = null;
        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                $binary = $candidate;
                break;
            }
        }
        $binary ??= (is_string($configured) && $configured !== '') ? $configured : (PHP_OS_FAMILY === 'Windows' ? 'python' : 'python3');

        if ($windowless && PHP_OS_FAMILY === 'Windows' && preg_match('/python(\.exe)?$/i', $binary)) {
            $windowlessBinary = preg_replace('/python(\.exe)?$/i', 'pythonw.exe', $binary);
            if (is_file($windowlessBinary)) {
                return $windowlessBinary;
            }
        }

        return $binary;
    }

    /**
     * @param  array{split?: float, tune?: bool, seasonal?: bool}  $options
     */
    public function train(Dataset $dataset, string $algorithm, array $options, ?User $user): MlModel
    {
        if (! array_key_exists($algorithm, MlModel::ALGORITHMS)) {
            throw new MlException('Unsupported algorithm.', 'invalid_request');
        }
        if (! $dataset->isTrainable()) {
            throw new MlException('This dataset is not valid for training (it needs at least '.config('store.ml.min_records').' valid records).', 'insufficient_data');
        }
        if (! is_file($dataset->absolutePath())) {
            throw new MlException('The dataset file is missing on the server.', 'dataset_missing');
        }

        $task = $dataset->task();
        $version = $this->nextVersion($task, $algorithm);
        $relative = "models/{$task}/{$algorithm}-{$version}.joblib";
        File::ensureDirectoryExists(dirname($this->baseDir().'/'.$relative));

        $split = (float) ($options['split'] ?? 0.8);
        $response = $this->run('train', [
            'task' => $task,
            'algorithm' => $algorithm,
            'base_dir' => $this->baseDir(),
            'dataset_path' => $dataset->absolutePath(),
            'output_path' => $this->baseDir().'/'.$relative,
            'split' => $split,
            'tune' => (bool) ($options['tune'] ?? false),
            'seasonal' => (bool) ($options['seasonal'] ?? false),
            'seed' => 42,
        ]);

        $metrics = $response['metrics'];

        $model = DB::transaction(function () use ($dataset, $task, $algorithm, $version, $relative, $response, $metrics, $split, $user) {
            MlModel::where('task', $task)->where('algorithm', $algorithm)->update(['is_active' => false]);

            return MlModel::create([
                'name' => MlModel::ALGORITHMS[$algorithm].' · '.($task === 'sales' ? 'Sales' : 'Product demand'),
                'task' => $task,
                'algorithm' => $algorithm,
                'version' => $version,
                'dataset_id' => $dataset->id,
                'file_path' => $relative,
                'file_sha256' => $response['sha256'],
                'file_size' => (int) $response['size'],
                'target' => $response['target'] ?? $dataset->target,
                'features' => $response['features'] ?? [],
                'params' => $response['params'] ?? [],
                'notes' => $response['notes'] ?? [],
                'eval_protocol' => $response['eval_protocol'] ?? null,
                'split' => $split,
                'train_points' => (int) $response['train_points'],
                'test_points' => (int) $response['test_points'],
                'dataset_records' => (int) $response['rows'],
                'mae' => $metrics['mae'],
                'rmse' => $metrics['rmse'],
                'mape' => $metrics['mape'],
                'wape' => $metrics['wape'] ?? null,
                'r2' => $metrics['r2'],
                'trained_through' => $response['trained_through'] ?? null,
                'status' => 'ready',
                'is_active' => true,
                'trained_by' => $user?->id,
                'trained_at' => now(),
            ]);
        });

        Log::channel('security')->info('ml.model_trained', ['model_id' => $model->id, 'task' => $task, 'algorithm' => $algorithm, 'user_id' => $user?->id]);

        return $model;
    }

    /**
     * @param  MlModel|list<MlModel>  $models  one model, or two to blend them ("both")
     * @param  array<string, mixed>  $params
     * @param  list<array<string, mixed>>  $products
     * @return array<string, mixed>
     */
    public function predict(MlModel|array $models, string $workflow, array $params, array $products = []): array
    {
        $models = is_array($models) ? $models : [$models];

        return $this->run('predict', [
            'workflow' => $workflow,
            'base_dir' => $this->baseDir(),
            'models' => array_map(fn (MlModel $model) => ['model_path' => $model->absolutePath(), 'sha256' => $model->file_sha256], $models),
            'params' => $params,
            'products' => $products,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function run(string $command, array $payload): array
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $decoded = null;

        // First try the windowless interpreter; if it produces no usable output, retry with the normal one.
        foreach (array_unique([$this->pythonBinary(true), $this->pythonBinary(false)]) as $binary) {
            [$stdout, $stderr, $exit] = $this->execute($binary, $command, $json);
            $decoded = json_decode(trim($stdout), true);
            if (is_array($decoded)) {
                break;
            }
            Log::error('ml.invalid_output', ['command' => $command, 'python' => $binary, 'exit' => $exit, 'stderr' => mb_substr(str_replace(base_path(), '[project]', $stderr), -800)]);
        }

        if (! is_array($decoded)) {
            throw new MlException(
                'Python could not run the analysis. Make sure Python 3.10+ is installed and run "pip install -r ml/requirements.txt" (set ML_PYTHON in .env if needed).',
                'python_unavailable'
            );
        }
        if (($decoded['ok'] ?? false) !== true) {
            if (($decoded['code'] ?? '') === 'internal_error') {
                Log::error('ml.internal_error', ['command' => $command, 'detail' => $decoded['error'] ?? '']);
                throw new MlException('The analysis failed unexpectedly. Check storage/logs for details.', 'internal_error');
            }
            throw new MlException((string) ($decoded['error'] ?? 'The analysis failed.'), (string) ($decoded['code'] ?? 'ml_error'));
        }

        return $decoded;
    }

    /**
     * Runs the interpreter with proc_open (argument array, no shell, JSON on stdin) like the Tempus bridge.
     * No "create_new_console" option is used, so no command window is opened.
     *
     * @return array{0: string, 1: string, 2: int}
     */
    private function execute(string $binary, string $command, string $json): array
    {
        $environment = array_merge(getenv() ?: [], [
            'PYTHONIOENCODING' => 'utf-8',
            'PYTHONUTF8' => '1',
            'PYTHONDONTWRITEBYTECODE' => '1',
        ]);
        if (PHP_OS_FAMILY === 'Windows') {
            // Python needs these to initialise when started from a service such as Apache.
            $environment['SystemRoot'] = getenv('SystemRoot') ?: 'C:\\Windows';
            $environment['WINDIR'] = getenv('WINDIR') ?: $environment['SystemRoot'];
        }

        $process = @proc_open(
            [$binary, base_path('ml/cli.py'), $command],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            base_path(),
            $environment,
            ['bypass_shell' => true]
        );
        if (! is_resource($process)) {
            return ['', 'Failed to start the process.', -1];
        }

        fwrite($pipes[0], $json);
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = $stderr = '';
        $deadline = microtime(true) + (int) config('store.ml.timeout');
        $open = [$pipes[1], $pipes[2]];

        while ($open !== []) {
            $read = $open;
            $write = $except = null;
            if (@stream_select($read, $write, $except, 0, 200000) === false) {
                break;
            }
            foreach ($read as $stream) {
                $chunk = fread($stream, 65536);
                if ($chunk !== false && $chunk !== '') {
                    $stream === $pipes[1] ? $stdout .= $chunk : $stderr .= $chunk;
                }
                if (feof($stream)) {
                    $open = array_values(array_filter($open, fn ($s) => $s !== $stream));
                }
            }
            if (microtime(true) > $deadline) {
                proc_terminate($process);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);
                throw new MlException('The analysis took too long and was stopped. Try a smaller dataset or disable tuning.', 'timeout');
            }
        }

        fclose($pipes[1]);
        fclose($pipes[2]);

        return [$stdout, $stderr, proc_close($process)];
    }

    private function nextVersion(string $task, string $algorithm): string
    {
        $prefix = now()->format('Y.m.d');
        $count = MlModel::where('task', $task)->where('algorithm', $algorithm)->where('version', 'like', $prefix.'.%')->count();

        return $prefix.'.'.($count + 1);
    }
}
