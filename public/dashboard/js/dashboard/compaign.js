(function($) {
    /* "use strict" */


 var dzChartlist = function(){
	//let draw = Chart.controllers.line.__super__.draw; //draw shadow
	var screenWidth = $(window).width();
	
	var Camp1 = function () {
		var options = {
			series: [
				{
					name: 'Net Profit',
					data: [400, 300, 900, 100,400,300,900,100, 400, 100, 400,300,900,200],
					/* radius: 30,	 */
				},
			],
			chart: {
				type: 'line',
				height: 90,
				width: 150,
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

			colors: ['#09BD3C'],
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
				colors: ['#09BD3C'],
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
						width: 100,
						height: 40,
					}
				},
			}]
		};

		var chartBar1 = new ApexCharts(document.querySelector("#Camp1"), options);
		chartBar1.render();

	}
	var Camp2 = function () {
		var options = {
			series: [
				{
					name: 'Net Profit',
					data: [400, 300, 900, 100,400,300,900,100, 400, 100, 400,300,900,200],
					/* radius: 30,	 */
				},
			],
			chart: {
				type: 'line',
				height: 90,
				width: 150,
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

			colors: ['#FD5353'],
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
				colors: ['#FD5353'],
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
						width: 100,
						height: 40,
					}
				},
			}]
		};

		var chartBar1 = new ApexCharts(document.querySelector("#Camp2"), options);
		chartBar1.render();

	}
	var Camp3 = function () {
		var options = {
			series: [
				{
					name: 'Net Profit',
					data: [400, 300, 900, 100,400,300,900,100, 400, 100, 400,300,900,200],
					/* radius: 30,	 */
				},
			],
			chart: {
				type: 'line',
				height: 90,
				width: 150,
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
						width: 100,
						height: 40,
					}
				},
			}]
		};

		var chartBar1 = new ApexCharts(document.querySelector("#Camp3"), options);
		chartBar1.render();

	}
	var Camp4 = function () {
		var options = {
			series: [
				{
					name: 'Net Profit',
					data: [400, 300, 900, 100,400,300,900,100, 400, 100, 400,300,900,200],
					/* radius: 30,	 */
				},
			],
			chart: {
				type: 'line',
				height: 90,
				width: 150,
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

			colors: ['#FD5353'],
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
				colors: ['#FD5353'],
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
						width: 100,
						height: 40,
					}
				},
			}]
		};

		var chartBar1 = new ApexCharts(document.querySelector("#Camp4"), options);
		chartBar1.render();

	}

	var donutChart = function () {
		$("span.donut").peity("donut", {
			width: "90",
			height: "90"
		});
	}




	/* Function ============ */
		return {
			init:function(){
			},
			
			
			load:function(){
				Camp1();
				donutChart();
				Camp2();
				Camp3();
				Camp4();
			},
			
			resize:function(){
				
			}
		}
	
	}();
		
	jQuery(window).on('load',function(){
		setTimeout(function(){
			dzChartlist.load();
		}, 1000); 
		
	});

	jQuery(window).on('resize',function(){
		
		
	});     

})(jQuery);