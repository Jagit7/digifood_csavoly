(function ($) {
	/* "use strict" */


	var dzChartlist = function () {
		//let draw = Chart.controllers.line.__super__.draw; //draw shadow
		var screenWidth = $(window).width();

	
		var chartBar = function () {

			var options = {
				series: [
					{
						name: 'Income',
						data: [50, 18, 70, 40],
						//radius: 12,	
					},
					{
						name: 'Expense',
						data: [80, 40, 55, 20]
					},

				],
				chart: {
					type: 'bar',
					height: 280,

					toolbar: {
						show: false,
					},

				},
				plotOptions: {
					bar: {
						horizontal: false,
						columnWidth: '57%',
						endingShape: "rounded",
						borderRadius: 8,
					},

				},
				states: {
					hover: {
						filter: 'none',
					}
				},
				colors: ['#09BD3C', '#FD5353'],
				dataLabels: {
					enabled: false,
				},
				markers: {
					shape: "circle",
				},

				legend: {
					show: true,
					fontSize: '12px',
					position: 'top',
					labels: {
						colors: '#000000',

					},
					markers: {
						width: 18,
						height: 18,
						strokeWidth: 10,
						strokeColor: '#fff',
						fillColors: undefined,
						radius: 12,
					}
				},
				responsive: [
					{
						breakpoint: 768,
						options: {
							plotOptions: {
								bar: {
									horizontal: true,
									columnWidth: '100%',
									borderRadius: 5
								},
							},
							legend: {
								position: 'bottom',
								horizontalAlign: 'center'
							}
						},
					}
				],
				stroke: {
					show: true,
					width: 4,
					curve: 'smooth',
					lineCap: 'round',
					colors: ['transparent']
				},
				grid: {
					borderColor: 'var(--border)',
				},
				xaxis: {
					position: 'bottom',
					categories: ['Sun', 'Mon', 'Tue', 'Wed'],
					labels: {
						show: false,
						style: {
							colors: '#787878',
							fontSize: '13px',
							fontFamily: 'poppins',
							fontWeight: 100,
							cssClass: 'apexcharts-xaxis-label',
						},

					},
					axisTicks: {
						show: false,
					},
					crosshairs: {
						show: false,
					},
					axisBorder: {
						show: false,
					},
				},

				yaxis: {
					labels: {
						style: {
							colors: '#787878',
							fontSize: '13px',
							fontFamily: 'poppins',
							fontWeight: 100,
							cssClass: 'apexcharts-xaxis-label',
						},
					},
				},
				fill: {
					type: 'gradient',
					gradient: {
						shade: 'white',
						type: "vertical",
						shadeIntensity: 0.2,
						gradientToColors: undefined, // optional, if not defined - uses the shades of same color in series
						inverseColors: true,
						opacityFrom: 1,
						opacityTo: 1,
						stops: [0, 50, 50],
						colorStops: []
					}
				},
				tooltip: {
					y: {
						formatter: function (val) {
							return "$ " + val + " thousands"
						}
					}
				},

			};

			if (jQuery("#chartBar").length > 0) {
				var chartBar1 = new ApexCharts(document.querySelector("#chartBar"), options);
				chartBar1.render();

				jQuery('.card-tabs .nav-link').on('click', function () {
					if ($(this).attr("href") == "#monthly") {
						chartBar1.updateSeries([{
							name: "Running",
							data: [40, 20, 10, 50, 60, 90, 80]
						},
						{
							name: "Cycling",
							data: [40, 50, 10, 20, 50, 90, 80]
						}]
						)
					} else if ($(this).attr("href") == "#weekly") {
						chartBar1.updateSeries([{
							name: "Running",
							data: [50, 30, 40, 30, 40, 70, 90]
						},
						{
							name: "Cycling",
							data: [30, 40, 30, 10, 60, 80, 90]
						}]
						)
					} else if ($(this).attr("href") == "#today") {
						chartBar1.updateSeries([{
							name: "Running",
							data: [30, 20, 30, 40, 50, 60, 70]
						},
						{
							name: "Cycling",
							data: [20, 60, 40, 60, 10, 50, 70]
						}]
						)
					}
				})

			}
		}

		var Insight = function () {
			var options = {
				series: [
					{
						name: 'Net Profit',
						data: [100, 400, 100, 500,200,300,500,100, 100, 300, 200,100,400,100],
						/* radius: 30,	 */
					},
				],
				chart: {
					type: 'line',
					height: 100,
					width: 400,
					toolbar: {
						show: false,
					},
					zoom: {
						enabled: false
					},
					sparkline: {
						enabled: true
					}

				},

				colors: ['var(--primary)'],
				dataLabels: {
					enabled: false,
				},

				legend: {
					show: false,
				},
				stroke: {
					show: true,
					width: 6,
					curve: 'smooth',
					colors: ['var(--primary)'],
				},

				grid: {
					show: false,
					borderColor: '#eee',
					padding: {
						top: 0,
						right: 0,
						bottom: 0,
						left: 0

					}
				},
				states: {
					normal: {
						filter: {
							type: 'none',
							value: 0
						}
					},
					hover: {
						filter: {
							type: 'none',
							value: 0
						}
					},
					active: {
						allowMultipleDataPointsSelection: false,
						filter: {
							type: 'none',
							value: 0
						}
					}
				},
				x: {
					categories: ['Jan', 'feb', 'Mar', 'Apr', 'May'],
					axisBorder: {
						show: false,
					},
					axisTicks: {
						show: false
					},
					labels: {
						show: false,
						style: {
							fontSize: '12px',
						}
					},
					crosshairs: {
						show: false,
						position: 'front',
						stroke: {
							width: 1,
							dashArray: 3
						}
					},
					tooltip: {
						enabled: true,
						formatter: undefined,
						offsetY: 0,
						style: {
							fontSize: '12px',
						}
					}
				},
				y: {
					show: false,
				},
				tooltip: {
					enabled: false,
					style: {
						fontSize: '12px',
					},
					y: {
						formatter: function (val) {
							return "$" + val + " thousands"
						}
					}
				},
				responsive: [{
					breakpoint: 1400,
					options: {
						chart: {
							width: 400,
							//height: 40,
						}
					},
				}]
			};

			var chartBar1 = new ApexCharts(document.querySelector("#Insight"), options);
			chartBar1.render();

		}

		var columnChart = function(){
			var options = {
				series: [{
					name: 'Instagram',
					data: [44, 55, 41, 67, 22, 43, 44, 55]
				}, {
					name: 'Facebook',
					data: [13, 23, 20, 8,  13, 27, 13, 23]
				}, {
					name: 'Twitter',
					data: [11, 17, 15, 15, 21, 14, 11, 17]
				}],
				chart: {
					type: 'bar',
					height: 365,
					stacked: true,
					toolbar: {
						show: false,
					}
				},
				responsive: [{
					breakpoint: 480,
					options: {
						legend: {
							position: 'bottom',
							offsetX: -10,
							offsetY: 0
						}
					}
				}],
				plotOptions: {
					bar: {
						horizontal: false,
						columnWidth: '20%',
						endingShape: "rounded",
						backgroundRadius: 10,
						colors: {
							backgroundBarColors: ['#f0f0f0', '#f0f0f0', '#f0f0f0', '#f0f0f0', '#f0f0f0', '#f0f0f0', '#f0f0f0'],
							backgroundBarOpacity: 1,
							backgroundBarRadius: 5,
						},
					},
					
				},
				colors:['#ff285c', '#5856ce', '#56c7ce'],
				xaxis: {
					show: true,
					
					axisBorder: {
						show: true,
					},
					
					labels: {
						style: {
							colors: '#333',
							fontSize: '13px',
							fontFamily: 'Poppins',
							fontWeight: 300,
							cssClass: 'apexcharts-xaxis-label',
						},
					},
					crosshairs: {
						show: false,
					},
					
					categories: ['01', '02', '03', '04', '05', '06', '07'],
				},
				yaxis: {
					show: false
				},
				grid: {
					show: false,
				},
				toolbar: {
					enabled: false,
				},
				dataLabels: {
				  enabled: false
				},
				legend: {
					position: 'bottom',
					offsetY: 5
				},
				fill: {
					opacity: 1
				}
			};
	
			var chart = new ApexCharts(document.querySelector("#columnChart"), options);
			chart.render();
		}

		var chartBalance = function () {

			var options = {
				series: [
					{
						name: 'Income',
						data: [50, 18, 70, 40],
						//radius: 12,	
					},
					{
						name: 'Expense',
						data: [80, 40, 55, 20]
					},

				],
				chart: {
					type: 'bar',
					height: 250,

					toolbar: {
						show: false,
					},

				},
				plotOptions: {
					bar: {
						horizontal: false,
						columnWidth: '57%',
						endingShape: "rounded",
						borderRadius: 8,
					},

				},
				states: {
					hover: {
						filter: 'none',
					}
				},
				colors: ['#56C7CE', 'var(--primary)'],
				dataLabels: {
					enabled: false,
				},
				markers: {
					shape: "circle",
				},

				legend: {
					show: true,
					fontSize: '12px',
					position: 'top',
					labels: {
						colors: '#000000',

					},
					markers: {
						width: 18,
						height: 18,
						strokeWidth: 10,
						strokeColor: '#fff',
						fillColors: undefined,
						radius: 12,
					}
				},
				responsive: [
					{
						breakpoint: 768,
						options: {
							plotOptions: {
								bar: {
									horizontal: true,
									columnWidth: '100%',
									borderRadius: 5
								},
							},
							legend: {
								position: 'bottom',
								horizontalAlign: 'center'
							}
						},
					}
				],
				stroke: {
					show: true,
					width: 4,
					curve: 'smooth',
					lineCap: 'round',
					colors: ['transparent']
				},
				grid: {
					borderColor: 'var(--border)',
				},
				xaxis: {
					position: 'bottom',
					categories: ['Sun', 'Mon', 'Tue', 'Wed'],
					labels: {
						show: false,
						style: {
							colors: '#787878',
							fontSize: '13px',
							fontFamily: 'poppins',
							fontWeight: 100,
							cssClass: 'apexcharts-xaxis-label',
						},

					},
					axisTicks: {
						show: false,
					},
					crosshairs: {
						show: false,
					},
					axisBorder: {
						show: false,
					},
				},

				yaxis: {
					labels: {
						style: {
							colors: '#787878',
							fontSize: '13px',
							fontFamily: 'poppins',
							fontWeight: 100,
							cssClass: 'apexcharts-xaxis-label',
						},
					},
				},
				fill: {
					type: 'gradient',
					gradient: {
						shade: 'white',
						type: "vertical",
						shadeIntensity: 0.2,
						gradientToColors: undefined, // optional, if not defined - uses the shades of same color in series
						inverseColors: true,
						opacityFrom: 1,
						opacityTo: 1,
						stops: [0, 50, 50],
						colorStops: []
					}
				},
				tooltip: {
					y: {
						formatter: function (val) {
							return "$ " + val + " thousands"
						}
					}
				},

			};

			if (jQuery("#chartBalance").length > 0) {
				var chartBar1 = new ApexCharts(document.querySelector("#chartBalance"), options);
				chartBar1.render();

				jQuery('.card-tabs .nav-link').on('click', function () {
					if ($(this).attr("href") == "#monthly") {
						chartBar1.updateSeries([{
							name: "Running",
							data: [40, 20, 10, 50, 60, 90, 80]
						},
						{
							name: "Cycling",
							data: [40, 50, 10, 20, 50, 90, 80]
						}]
						)
					} else if ($(this).attr("href") == "#weekly") {
						chartBar1.updateSeries([{
							name: "Running",
							data: [50, 30, 40, 30, 40, 70, 90]
						},
						{
							name: "Cycling",
							data: [30, 40, 30, 10, 60, 80, 90]
						}]
						)
					} else if ($(this).attr("href") == "#today") {
						chartBar1.updateSeries([{
							name: "Running",
							data: [30, 20, 30, 40, 50, 60, 70]
						},
						{
							name: "Cycling",
							data: [20, 60, 40, 60, 10, 50, 70]
						}]
						)
					}
				})

			}
		}
		var summaryChart = function(){
			var summary = document.getElementById("summary");
				if (summary !== null) {
					var summaryData = [{
							first: [ 30, 35, 40, 30, 38, 32, 42, 32, 42, 35, 40, 30, 38, 32, 42, 30, 35, 22, 30, 45]
						},
						{
							first: [35, 35, 40, 30, 38, 40, 30, 38, 32, 42, 32, 42, 32, 42, 30, 35, 22, 30, 45, 30]
						},
						{
							first: [35, 40, 40, 30, 38, 32, 42, 32, 42, 30, 38, 32, 42, 30, 35, 22, 30, 45, 30, 35]
						},
						{
							first: [35, 40, 30, 38, 32, 42, 30, 35, 22, 30, 45, 30, 35, 40, 30, 38, 32, 42, 32, 42]
						}
					];
					summary.height = 400;
					
					var config = {
						type: "line",
						data: {
							labels: [
								"August",
								"September",
								"October",
								"November",
								"December",
								"January",
								"February",
								"August",
								"September",
								"October",
								
							],
							datasets: [
								{
									label: "My First dataset",
									data: summaryData[0].first,
									borderColor: 'rgba(26, 51, 213, 0)',
									backgroundColor: 'rgba(141, 125, 232, 1)',
									fill: true
								}
							]
						},
						options: {
							responsive: true,
							maintainAspectRatio: false,
							elements: {
									point:{
										radius: 0
									}
							},
							plugins: {
								legend: {
									display: false
								},
								tooltip: {
									mode: "index",
									intersect: false,
								},
								
							},
							elements : {
								line: {
								   tension: .4
							   }
						   },
						   scales: {
								y: {
									gridColor: "navy",
									gridLines: {
										color: "rgba(0,0,0,0.1)",
										height: 50,
										drawBorder: true
									},
									ticks: {
										fontColor: "#3e4954",
										max: 100,
										min: 0,
										stepSize: 5
									},
								},
								x: {
									barPercentage: 0.3,
									gridLines: {
										display: false,
										zeroLineColor: "transparent"
									},
									ticks: {
										stepSize:0,
										fontColor: "#3e4954",
										fontFamily: "Nunito, sans-serif"
									}
								}
							},
							tooltips: {
								enabled: false,
								mode: "index",
								intersect: false,
								titleFontColor: "#888",
								bodyFontColor: "#555",
								titleFontSize: 12,
								bodyFontSize: 15,
								backgroundColor: "rgba(256,256,256,0.95)",
								displayColors: true,
								xPadding: 10,
								yPadding: 7,
								borderColor: "rgba(220, 220, 220, 0.9)",
								borderWidth: 2,
								caretSize: 6,
								caretPadding: 10
							}
						}
					};
	
					var ctx = document.getElementById("summary").getContext("2d");
					var myLine = new Chart(ctx, config);
					var items = document.querySelectorAll("#user-summary .nav-tabs .nav-item");
					items.forEach(function(item, index) {
						item.addEventListener("click", function() {
							config.data.datasets[0].data = summaryData[index].first;
							myLine.update();
						});
					});
				}
		
			
		}
		var chartBalance = function () {

			var options = {
				series: [
					{
						name: 'Income',
						data: [50, 18, 70, 40],
						//radius: 12,	
					},
					{
						name: 'Expense',
						data: [80, 40, 55, 20]
					},

				],
				chart: {
					type: 'bar',
					height: 250,

					toolbar: {
						show: false,
					},

				},
				plotOptions: {
					bar: {
						horizontal: false,
						columnWidth: '57%',
						endingShape: "rounded",
						borderRadius: 8,
					},

				},
				states: {
					hover: {
						filter: 'none',
					}
				},
				colors: ['#56C7CE', 'var(--primary)'],
				dataLabels: {
					enabled: false,
				},
				markers: {
					shape: "circle",
				},

				legend: {
					show: true,
					fontSize: '12px',
					position: 'top',
					labels: {
						colors: '#000000',

					},
					markers: {
						width: 18,
						height: 18,
						strokeWidth: 10,
						strokeColor: '#fff',
						fillColors: undefined,
						radius: 12,
					}
				},
				responsive: [
					{
						breakpoint: 768,
						options: {
							plotOptions: {
								bar: {
									horizontal: true,
									columnWidth: '100%',
									borderRadius: 5
								},
							},
							legend: {
								position: 'bottom',
								horizontalAlign: 'center'
							}
						},
					}
				],
				stroke: {
					show: true,
					width: 4,
					curve: 'smooth',
					lineCap: 'round',
					colors: ['transparent']
				},
				grid: {
					borderColor: 'var(--border)',
				},
				xaxis: {
					position: 'bottom',
					categories: ['Sun', 'Mon', 'Tue', 'Wed'],
					labels: {
						show: false,
						style: {
							colors: '#787878',
							fontSize: '13px',
							fontFamily: 'poppins',
							fontWeight: 100,
							cssClass: 'apexcharts-xaxis-label',
						},

					},
					axisTicks: {
						show: false,
					},
					crosshairs: {
						show: false,
					},
					axisBorder: {
						show: false,
					},
				},

				yaxis: {
					labels: {
						style: {
							colors: '#787878',
							fontSize: '13px',
							fontFamily: 'poppins',
							fontWeight: 100,
							cssClass: 'apexcharts-xaxis-label',
						},
					},
				},
				fill: {
					type: 'gradient',
					gradient: {
						shade: 'white',
						type: "vertical",
						shadeIntensity: 0.2,
						gradientToColors: undefined, // optional, if not defined - uses the shades of same color in series
						inverseColors: true,
						opacityFrom: 1,
						opacityTo: 1,
						stops: [0, 50, 50],
						colorStops: []
					}
				},
				tooltip: {
					y: {
						formatter: function (val) {
							return "$ " + val + " thousands"
						}
					}
				},

			};

			if (jQuery("#chartBalance").length > 0) {
				var chartBar1 = new ApexCharts(document.querySelector("#chartBalance"), options);
				chartBar1.render();

				jQuery('.card-tabs .nav-link').on('click', function () {
					if ($(this).attr("href") == "#monthly") {
						chartBar1.updateSeries([{
							name: "Running",
							data: [40, 20, 10, 50, 60, 90, 80]
						},
						{
							name: "Cycling",
							data: [40, 50, 10, 20, 50, 90, 80]
						}]
						)
					} else if ($(this).attr("href") == "#weekly") {
						chartBar1.updateSeries([{
							name: "Running",
							data: [50, 30, 40, 30, 40, 70, 90]
						},
						{
							name: "Cycling",
							data: [30, 40, 30, 10, 60, 80, 90]
						}]
						)
					} else if ($(this).attr("href") == "#today") {
						chartBar1.updateSeries([{
							name: "Running",
							data: [30, 20, 30, 40, 50, 60, 70]
						},
						{
							name: "Cycling",
							data: [20, 60, 40, 60, 10, 50, 70]
						}]
						)
					}
				})

			}
		}
		var NewCustomers = function () {
			var options = {
				series: [
					{
						name: 'Net Profit',
						data: [100, 300, 100, 400,100],
						/* radius: 30,	 */
					},
				],
				chart: {
					type: 'line',
					height: 70,
					width: 120,
					toolbar: {
						show: false,
					},
					zoom: {
						enabled: false
					},
					sparkline: {
						enabled: true
					}

				},

				colors: ['var(--primary)'],
				dataLabels: {
					enabled: false,
				},

				legend: {
					show: false,
				},
				stroke: {
					show: true,
					width: 8,
					curve: 'smooth',
					colors: ['var(--primary)'],
				},

				grid: {
					show: false,
					borderColor: '#eee',
					padding: {
						top: 0,
						right: 0,
						bottom: 0,
						left: 0

					}
				},
				states: {
					normal: {
						filter: {
							type: 'none',
							value: 0
						}
					},
					hover: {
						filter: {
							type: 'none',
							value: 0
						}
					},
					active: {
						allowMultipleDataPointsSelection: false,
						filter: {
							type: 'none',
							value: 0
						}
					}
				},
				x: {
					categories: ['Jan', 'feb', 'Mar', 'Apr', 'May'],
					axisBorder: {
						show: false,
					},
					axisTicks: {
						show: false
					},
					labels: {
						show: false,
						style: {
							fontSize: '12px',
						}
					},
					crosshairs: {
						show: false,
						position: 'front',
						stroke: {
							width: 1,
							dashArray: 3
						}
					},
					tooltip: {
						enabled: true,
						formatter: undefined,
						offsetY: 0,
						style: {
							fontSize: '12px',
						}
					}
				},
				y: {
					show: false,
				},
				tooltip: {
					enabled: false,
					style: {
						fontSize: '12px',
					},
					y: {
						formatter: function (val) {
							return "$" + val + " thousands"
						}
					}
				},
				responsive: [{
					breakpoint: 1400,
					options: {
						chart: {
							width: 70,
							height: 40,
						}
					},
				}]
			};

			var chartBar1 = new ApexCharts(document.querySelector("#NewCustomers"), options);
			chartBar1.render();

		}
		var OverviewChart = function () {
			if ($('#overview-chart').length > 0) {
				var options = {
					series: [37.5, 7.8, 12.5, 5.2],
					chart: {
						type: 'donut',
						parentHeightOffset: 0,
						sparkline: {
							enabled: true,
						},
					},
					colors: ["var(--primary)", "#F87C22", "#50D37D", "#AB31E4"],
					legend: {
						show: true,
						position: 'bottom',
						fontSize: '14px',
						labels: {
							colors: '#000', // Color of the legend labels
							useSeriesColors: false
						},
						// Custom labels for each series
						formatter: function(seriesName, opts) {
							const labels = ["Main Card", "Orange Card", "Purple Card", "Green Card"];
							return labels[opts.seriesIndex];
						},
						markers: {
							height:19,
							width:19,
						},
					},
					stroke: {
						width: 0,
					},
					dataLabels: {
						enabled: false,
					},
					plotOptions: {
						pie: {
							size: 100,
							width:60,
							donut: {
								labels: {
									show: false,
									name: {
										show: false,
									},
									value: {
										show: true,
										formatter: function (val) {
											return val + "%"
										}
									},
									total: {
										show: true,
										formatter: function (w) {
											return w.globals.seriesTotals[0] + "%"
										}
									}
								}
							}
						}
					}
				};
			
				var chart = new ApexCharts(document.querySelector("#overview-chart"), options);
				chart.render();
			}
			
		}
		/* Function ============ */
		return {
			init: function () {
			},


			load: function () {
				
				
				chartBar();
				Insight();
				columnChart();
				summaryChart();
				chartBalance();
				NewCustomers();
				OverviewChart();
			},

			resize: function () {
				OverviewChart();
			}
		}

	}();
	jQuery(window).on('load', function () {
		setTimeout(function () {
			dzChartlist.load();
		}, 1000);

	});

	jQuery(window).on('resize', function () {


	});

})(jQuery);