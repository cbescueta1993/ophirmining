<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>XRP Price Tracker</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #151924;
            color: #e0e3eb;
        }
        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .price-info {
            display: flex;
            flex-direction: column;
        }
        .symbol {
            font-size: 24px;
            font-weight: bold;
        }
        .current-price {
            font-size: 32px;
            font-weight: bold;
            margin: 10px 0;
        }
        .price-change {
            font-size: 16px;
        }
        .positive {
            color: #26a69a;
        }
        .negative {
            color: #ef5350;
        }
        .chart-container {
            position: relative;
            height: 400px;
            width: 100%;
            background-color: #1e222d;
            border-radius: 5px;
            padding: 20px;
            box-sizing: border-box;
            margin-bottom: 20px;
        }
        .indicator-container {
            position: relative;
            height: 150px;
            width: 100%;
            background-color: #1e222d;
            border-radius: 5px;
            padding: 20px;
            box-sizing: border-box;
        }
        .loading {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100%;
            width: 100%;
            position: absolute;
            top: 0;
            left: 0;
            background-color: rgba(30, 34, 45, 0.8);
            color: #e0e3eb;
            font-size: 18px;
            z-index: 10;
        }
        .tooltip {
            position: absolute;
            display: none;
            background-color: #2a2e39;
            border-radius: 4px;
            padding: 8px;
            color: #e0e3eb;
            font-size: 12px;
            pointer-events: none;
            z-index: 100;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="price-info">
                <div class="symbol">XRP/USDT - Binance Futures</div>
                <div class="current-price" id="current-price">Loading...</div>
                <div class="price-change" id="price-change">Loading...</div>
            </div>
        </div>
        
        <div class="chart-container">
            <div class="loading" id="price-loading">Loading price data...</div>
            <canvas id="priceChart"></canvas>
        </div>
        
        <div class="indicator-container">
            <div class="loading" id="rsi-loading">Loading RSI data...</div>
            <canvas id="rsiChart"></canvas>
        </div>
        
        <div class="tooltip" id="tooltip"></div>
    </div>

    <script>
        class CryptoChart {
            constructor(options) {
                this.symbol = options.symbol || 'XRPUSDT';
                this.interval = options.interval || '1h';
                this.days = options.days || 7;
                this.priceChartId = options.priceChartId || 'priceChart';
                this.rsiChartId = options.rsiChartId || 'rsiChart';
                this.priceElementId = options.priceElementId || 'current-price';
                this.priceChangeElementId = options.priceChangeElementId || 'price-change';
                this.priceLoadingId = options.priceLoadingId || 'price-loading';
                this.rsiLoadingId = options.rsiLoadingId || 'rsi-loading';
                this.tooltipId = options.tooltipId || 'tooltip';
                
                this.priceData = [];
                this.rsiData = [];
                this.rsiPeriod = 14;
                this.priceChart = null;
                this.rsiChart = null;
                
                this.priceChartEl = document.getElementById(this.priceChartId);
                this.rsiChartEl = document.getElementById(this.rsiChartId);
                this.priceElement = document.getElementById(this.priceElementId);
                this.priceChangeElement = document.getElementById(this.priceChangeElementId);
                this.priceLoadingElement = document.getElementById(this.priceLoadingId);
                this.rsiLoadingElement = document.getElementById(this.rsiLoadingId);
                this.tooltipElement = document.getElementById(this.tooltipId);
                
                this.init();
            }
            
            async init() {
                await this.fetchData();
                this.calculateRSI();
                this.checkRSIDivergence();
                this.renderCharts();
                this.updatePriceInfo();
                
                // Update every minute
                setInterval(async () => {
                    await this.fetchData();
                    this.calculateRSI();
                    this.checkRSIDivergence();
                    this.updateCharts();
                    this.updatePriceInfo();
                }, 60000);
            }
            
            async fetchData() {
                try {
                    this.priceLoadingElement.style.display = 'flex';
                    this.rsiLoadingElement.style.display = 'flex';
                    
                    const endTime = Date.now();
                    const startTime = endTime - (this.days * 24 * 60 * 60 * 1000);
                    
                    const url = `https://api.binance.com/api/v3/klines?symbol=${this.symbol}&interval=${this.interval}&startTime=${startTime}&endTime=${endTime}&limit=1000`;
                    
                    const response = await fetch(url);
                    const data = await response.json();
                    
                    this.priceData = data.map(item => ({
                        time: new Date(item[0]),
                        open: parseFloat(item[1]),
                        high: parseFloat(item[2]),
                        low: parseFloat(item[3]),
                        close: parseFloat(item[4]),
                        volume: parseFloat(item[5])
                    }));
                    
                    this.priceLoadingElement.style.display = 'none';
                    this.rsiLoadingElement.style.display = 'none';
                } catch (error) {
                    console.error('Error fetching data:', error);
                    this.priceLoadingElement.textContent = 'Error loading data. Please try again.';
                    this.rsiLoadingElement.textContent = 'Error loading data. Please try again.';
                }
            }
            
            calculateRSI() {
                if (this.priceData.length <= this.rsiPeriod) {
                    return;
                }
                
                let gains = 0;
                let losses = 0;
                
                // Calculate first average gain and loss
                for (let i = 1; i <= this.rsiPeriod; i++) {
                    const change = this.priceData[i].close - this.priceData[i - 1].close;
                    if (change >= 0) {
                        gains += change;
                    } else {
                        losses += Math.abs(change);
                    }
                }
                
                let avgGain = gains / this.rsiPeriod;
                let avgLoss = losses / this.rsiPeriod;
                
                this.rsiData = [];
                
                // Calculate RSI using Wilder's smoothing method
                for (let i = this.rsiPeriod; i < this.priceData.length; i++) {
                    const change = this.priceData[i].close - this.priceData[i - 1].close;
                    const currentGain = change >= 0 ? change : 0;
                    const currentLoss = change < 0 ? Math.abs(change) : 0;
                    
                    avgGain = ((avgGain * (this.rsiPeriod - 1)) + currentGain) / this.rsiPeriod;
                    avgLoss = ((avgLoss * (this.rsiPeriod - 1)) + currentLoss) / this.rsiPeriod;
                    
                    const rs = avgGain / (avgLoss === 0 ? 0.001 : avgLoss);
                    const rsi = 100 - (100 / (1 + rs));
                    
                    this.rsiData.push({
                        time: this.priceData[i].time,
                        value: rsi,
                        divergence: null
                    });
                }
            }
            
            checkRSIDivergence() {
                if (this.rsiData.length < 2 || this.priceData.length < this.rsiPeriod + 2) {
                    return;
                }
                
                // Check for bullish divergence (price making lower lows but RSI making higher lows)
                for (let i = 1; i < this.rsiData.length; i++) {
                    const priceIndex = i + this.rsiPeriod;
                    
                    if (
                        this.priceData[priceIndex].low < this.priceData[priceIndex - 1].low &&
                        this.rsiData[i].value > this.rsiData[i - 1].value
                    ) {
                        this.rsiData[i].divergence = 'bullish';
                    }
                    // Check for bearish divergence (price making higher highs but RSI making lower highs)
                    else if (
                        this.priceData[priceIndex].high > this.priceData[priceIndex - 1].high &&
                        this.rsiData[i].value < this.rsiData[i - 1].value
                    ) {
                        this.rsiData[i].divergence = 'bearish';
                    }
                }
            }
            
            renderCharts() {
                const times = this.priceData.map(item => item.time);
                const prices = this.priceData.map(item => item.close);
                const rsiValues = this.rsiData.map(item => item.value);
                const rsiTimes = this.rsiData.map((_, i) => this.priceData[i + this.rsiPeriod].time);
                
                // Setup price chart
                const priceCtx = this.priceChartEl.getContext('2d');
                
                this.priceChart = new Chart(priceCtx, {
                    type: 'line',
                    data: {
                        labels: times,
                        datasets: [{
                            label: 'XRP/USDT',
                            data: prices,
                            borderColor: '#2196f3',
                            borderWidth: 2,
                            pointRadius: 0,
                            pointHoverRadius: 5,
                            pointHoverBackgroundColor: '#2196f3',
                            fill: false,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            mode: 'index',
                            intersect: false,
                        },
                        scales: {
                            x: {
                                type: 'time',
                                time: {
                                    unit: 'day',
                                    displayFormats: {
                                        day: 'MMM DD'
                                    }
                                },
                                grid: {
                                    color: 'rgba(255, 255, 255, 0.1)'
                                },
                                ticks: {
                                    color: '#9fa4ae'
                                }
                            },
                            y: {
                                grid: {
                                    color: 'rgba(255, 255, 255, 0.1)'
                                },
                                ticks: {
                                    color: '#9fa4ae'
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                enabled: false,
                                external: this.externalTooltipHandler.bind(this)
                            }
                        }
                    }
                });
                
                // Setup RSI chart
                const rsiCtx = this.rsiChartEl.getContext('2d');
                
                const bullishDivergencePoints = [];
                const bearishDivergencePoints = [];
                
                this.rsiData.forEach((item, i) => {
                    if (item.divergence === 'bullish') {
                        bullishDivergencePoints.push({
                            x: rsiTimes[i],
                            y: item.value
                        });
                    } else if (item.divergence === 'bearish') {
                        bearishDivergencePoints.push({
                            x: rsiTimes[i],
                            y: item.value
                        });
                    }
                });
                
                this.rsiChart = new Chart(rsiCtx, {
                    type: 'line',
                    data: {
                        labels: rsiTimes,
                        datasets: [
                            {
                                label: 'RSI',
                                data: rsiValues,
                                borderColor: '#7e57c2',
                                borderWidth: 2,
                                pointRadius: 0,
                                pointHoverRadius: 5,
                                pointHoverBackgroundColor: '#7e57c2',
                                fill: false,
                            },
                            {
                                label: 'Bullish Divergence',
                                data: bullishDivergencePoints,
                                borderColor: '#26a69a',
                                backgroundColor: '#26a69a',
                                pointRadius: 5,
                                pointStyle: 'circle',
                                showLine: false
                            },
                            {
                                label: 'Bearish Divergence',
                                data: bearishDivergencePoints,
                                borderColor: '#ef5350',
                                backgroundColor: '#ef5350',
                                pointRadius: 5,
                                pointStyle: 'circle',
                                showLine: false
                            },
                            {
                                label: 'Overbought (70)',
                                data: rsiTimes.map(() => 70),
                                borderColor: 'rgba(255, 152, 0, 0.5)',
                                borderWidth: 1,
                                borderDash: [5, 5],
                                pointRadius: 0,
                                fill: false,
                            },
                            {
                                label: 'Oversold (30)',
                                data: rsiTimes.map(() => 30),
                                borderColor: 'rgba(255, 152, 0, 0.5)',
                                borderWidth: 1,
                                borderDash: [5, 5],
                                pointRadius: 0,
                                fill: false,
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            mode: 'index',
                            intersect: false,
                        },
                        scales: {
                            x: {
                                type: 'time',
                                time: {
                                    unit: 'day',
                                    displayFormats: {
                                        day: 'MMM DD'
                                    }
                                },
                                grid: {
                                    color: 'rgba(255, 255, 255, 0.1)'
                                },
                                ticks: {
                                    color: '#9fa4ae'
                                }
                            },
                            y: {
                                min: 0,
                                max: 100,
                                grid: {
                                    color: 'rgba(255, 255, 255, 0.1)'
                                },
                                ticks: {
                                    color: '#9fa4ae'
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                labels: {
                                    color: '#e0e3eb',
                                    filter: function(legendItem) {
                                        return legendItem.text !== 'Overbought (70)' && legendItem.text !== 'Oversold (30)';
                                    }
                                }
                            },
                            tooltip: {
                                enabled: false,
                                external: this.externalTooltipHandler.bind(this)
                            }
                        }
                    }
                });
            }
            
            updateCharts() {
                if (!this.priceChart || !this.rsiChart) return;
                
                const times = this.priceData.map(item => item.time);
                const prices = this.priceData.map(item => item.close);
                const rsiValues = this.rsiData.map(item => item.value);
                const rsiTimes = this.rsiData.map((_, i) => this.priceData[i + this.rsiPeriod].time);
                
                // Update price chart
                this.priceChart.data.labels = times;
                this.priceChart.data.datasets[0].data = prices;
                this.priceChart.update();
                
                // Update RSI chart
                this.rsiChart.data.labels = rsiTimes;
                this.rsiChart.data.datasets[0].data = rsiValues;
                
                const bullishDivergencePoints = [];
                const bearishDivergencePoints = [];
                
                this.rsiData.forEach((item, i) => {
                    if (item.divergence === 'bullish') {
                        bullishDivergencePoints.push({
                            x: rsiTimes[i],
                            y: item.value
                        });
                    } else if (item.divergence === 'bearish') {
                        bearishDivergencePoints.push({
                            x: rsiTimes[i],
                            y: item.value
                        });
                    }
                });
                
                this.rsiChart.data.datasets[1].data = bullishDivergencePoints;
                this.rsiChart.data.datasets[2].data = bearishDivergencePoints;
                this.rsiChart.update();
            }
            
            updatePriceInfo() {
                if (this.priceData.length === 0) return;
                
                const currentPrice = this.priceData[this.priceData.length - 1].close;
                const previousPrice = this.priceData[this.priceData.length - 2].close;
                const priceChange = currentPrice - previousPrice;
                const priceChangePercent = (priceChange / previousPrice) * 100;
                
                this.priceElement.textContent = `$${currentPrice.toFixed(4)}`;
                
                const changeText = `${priceChange >= 0 ? '+' : ''}${priceChange.toFixed(4)} (${priceChangePercent.toFixed(2)}%)`;
                this.priceChangeElement.textContent = changeText;
                
                if (priceChange >= 0) {
                    this.priceChangeElement.classList.add('positive');
                    this.priceChangeElement.classList.remove('negative');
                } else {
                    this.priceChangeElement.classList.add('negative');
                    this.priceChangeElement.classList.remove('positive');
                }
            }
            
            externalTooltipHandler(context) {
                const { chart, tooltip } = context;
                
                if (tooltip.opacity === 0) {
                    this.tooltipElement.style.display = 'none';
                    return;
                }
                
                const position = chart.canvas.getBoundingClientRect();
                
                this.tooltipElement.style.display = 'block';
                this.tooltipElement.style.left = position.left + window.pageXOffset + tooltip.caretX + 'px';
                this.tooltipElement.style.top = position.top + window.pageYOffset + tooltip.caretY + 'px';
                
                while (this.tooltipElement.firstChild) {
                    this.tooltipElement.firstChild.remove();
                }
                
                const index = tooltip.dataPoints[0].dataIndex;
                let content = '';
                
                if (chart === this.priceChart) {
                    const item = this.priceData[index];
                    content = `
                        <div>Date: ${moment(item.time).format('MMM DD, YYYY HH:mm')}</div>
                        <div>Price: $${item.close.toFixed(4)}</div>
                        <div>Open: $${item.open.toFixed(4)}</div>
                        <div>High: $${item.high.toFixed(4)}</div>
                        <div>Low: $${item.low.toFixed(4)}</div>
                    `;
                } else if (chart === this.rsiChart) {
                    // Adjust index since RSI data starts after rsiPeriod
                    const rsiIndex = index - this.rsiPeriod >= 0 ? index - this.rsiPeriod : 0;
                    if (rsiIndex >= 0 && rsiIndex < this.rsiData.length) {
                        const item = this.rsiData[rsiIndex];
                        content = `
                            <div>Date: ${moment(item.time).format('MMM DD, YYYY HH:mm')}</div>
                            <div>RSI: ${item.value.toFixed(2)}</div>
                        `;
                        
                        if (item.divergence === 'bullish') {
                            content += `<div style="color: #26a69a;">Bullish Divergence</div>`;
                        } else if (item.divergence === 'bearish') {
                            content += `<div style="color: #ef5350;">Bearish Divergence</div>`;
                        }
                    }
                }
                
                this.tooltipElement.innerHTML = content;
            }
        }
        
        // Initialize the chart
        document.addEventListener('DOMContentLoaded', () => {
            const cryptoChart = new CryptoChart({
                symbol: 'XRPUSDT',
                interval: '1h',
                days: 7
            });
        });
    </script>
</body>
</html>