<script lang="ts">
    import * as Chart from "../../lib/components/ui/chart/index.js";
    import { Area, AreaChart, LinearGradient } from "layerchart";
    import { scaleUtc } from "d3-scale";
    import { curveMonotoneX } from "d3-shape";

    export let rows: Array<{ date: string; total: number }> = [];
    export let formatValue: (value: number) => string = (value) => value.toLocaleString();
    export let formatDate: (value: Date) => string = (value) => value.toLocaleDateString();
    // Shorter labels for the x-axis (the tooltip keeps the full date).
    export let formatAxisDate: (value: Date) => string = formatDate;

    $: chartData = rows.map((row) => ({ x: new Date(`${row.date}T00:00:00`), y: Number(row.total) }));
    $: average = chartData.length ? chartData.reduce((sum, point) => sum + point.y, 0) / chartData.length : 0;
    $: chartDataWithAverage = chartData.map((point) => ({ ...point, average }));
    $: chartConfig = {
        data: { label: "Sales", color: "var(--color-primary)" },
    };
</script>

{#if chartData.length}
    <Chart.Container config={chartConfig} class="h-64 w-full min-w-0">
        <AreaChart
            data={chartDataWithAverage}
            x="x"
            xScale={scaleUtc()}
            padding={{ left: 56, bottom: 44, right: 12 }}
            series={[
                { key: "y", label: chartConfig.data.label, color: chartConfig.data.color },
                { key: "average", label: "Average", color: "var(--muted-foreground)" },
            ]}
            axis
            props={{
                area: { curve: curveMonotoneX, "fill-opacity": 0.4, line: { class: "stroke-2" }, motion: "tween" },
                xAxis: { format: (value) => formatAxisDate(value as Date), tickLabelProps: { rotate: -40, textAnchor: "end", verticalAnchor: "middle", dy: 6 } },
                yAxis: { format: (value) => formatValue(Number(value)) },
                grid: { style: "stroke-dasharray: 2", class: "stroke-1" },
            }}
        >
            {#snippet tooltip()}
                <Chart.Tooltip labelFormatter={(value) => formatDate(value as Date)} indicator="line">
                    {#snippet formatter({ value, name })}
                        <span class="text-muted-foreground">{name}</span>
                        <span class="font-mono font-medium tabular-nums">{formatValue(Number(value))}</span>
                    {/snippet}
                </Chart.Tooltip>
            {/snippet}
            {#snippet marks({ context })}
                {#each context.series.visibleSeries as series (series.key)}
                    {#if series.key === "y"}
                        <LinearGradient
                            stops={[
                                "color-mix(in lch, var(--color-primary) 55%, transparent)",
                                "color-mix(in lch, var(--color-primary) 4%, transparent)",
                            ]}
                            vertical
                        >
                            {#snippet children({ gradient })}
                                <Area seriesKey={series.key} curve={curveMonotoneX} fillOpacity={0.8} fill={gradient} line={{ class: "stroke-2 drop-shadow-[0_2px_4px_rgba(0,0,0,0.12)]" }} motion="tween" />
                            {/snippet}
                        </LinearGradient>
                    {:else}
                        <Area seriesKey={series.key} curve={curveMonotoneX} fill="none" line={{ stroke: "var(--color-muted-foreground)", strokeWidth: 1, "stroke-dasharray": "6 4" }} />
                    {/if}
                {/each}
            {/snippet}
        </AreaChart>
    </Chart.Container>
{:else}
    <div class="flex h-64 items-center justify-center text-sm text-muted-foreground">No sales in this period.</div>
{/if}
