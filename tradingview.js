class TradingViewXRP {
    constructor(chartId) {
        this.chartId = chartId;
        this.chart = null;
        this.apiUrl = "https://api.coingecko.com/api/v3/coins/ripple/market_chart?vs_currency=usd&days=7";
    }

    async fetchData() {
        const response = await fetch(this.apiUrl);
        const data = await response.json();
        return data.prices.map(([timestamp, price]) => ({
            x: new Date(timestamp),
            y: price
        }));
    }

    calculateRSI(data, period = 14) {
        let gains = [], losses = [], rsiValues = [];
        for (let i = 1; i < data.length; i++) {
            let change = data[i].y - data[i - 1].y;
            gains.push(change > 0 ? change : 0);
            losses.push(change < 0 ? Math.abs(change) : 0);
        }

        for (let i = period; i < data.length; i++) {
            let avgGain = gains.slice(i - period, i).reduce((a, b) => a + b, 0) / period;
            let avgLoss = losses.slice(i - period, i).reduce((a, b) => a + b, 0) / period;
            let rs = avgLoss === 0 ? 100 : avgGain / avgLoss;
            let rsi = 100 - (100 / (1 + rs));
            rsiValues.push({ x: data[i].x, y: rsi });
        }
        return rsiValues;
    }

    detectDivergence(priceData, rsiData) {
        let divergences = [];
        for (let i = 1; i < rsiData.length; i++) {
            if ((priceData[i].y > priceData[i - 1].y && rsiData[i].y < rsiData[i - 1].y) ||
                (priceData[i].y < priceData[i - 1].y && rsiData[i].y > rsiData[i - 1].y)) {
                divergences.push({ x: rsiData[i].x, y: rsiData[i].y });
            }
        }
        return divergences;
    }

    async renderChart() {
        const priceData = await this.fetchData();
        const rsiData = this.calculateRSI(priceData);
        const divergenceData = this.detectDivergence(priceData, rsiData);

        const ctx = document.getElementById(this.chartId).getContext("2d");
        this.chart = new Chart(ctx, {
            type: "line",
            data: {
                datasets: [
                    {
                        label: "XRP Price (USD)",
                        data: priceData,
                        borderColor: "blue",
                        borderWidth: 2,
                        fill: false,
                        yAxisID: "y-axis-price"
                    },
                    {
                        label: "RSI (14)",
                        data: rsiData,
                        borderColor: "green",
                        borderWidth: 2,
                        fill: false,
                        yAxisID: "y-axis-rsi"
                    },
                    {
                        label: "RSI Divergence",
                        data: divergenceData,
                        borderColor: "red",
                        pointRadius: 5,
                        pointBackgroundColor: "red",
                        type: "scatter",
                        yAxisID: "y-axis-rsi"
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    x: {
                        type: "time",
                        time: { unit: "day" },
                        title: { display: true, text: "Date" }
                    },
                    "y-axis-price": {
                        position: "left",
                        title: { display: true, text: "Price (USD)" }
                    },
                    "y-axis-rsi": {
                        position: "right",
                        title: { display: true, text: "RSI" },
                        min: 0,
                        max: 100
                    }
                }
            }
        });
    }
}

document.addEventListener("DOMContentLoaded", () => {
    const tradingView = new TradingViewXRP("xrpChart");
    tradingView.renderChart();
});
