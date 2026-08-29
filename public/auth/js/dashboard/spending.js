(function($){

var dzProfile = function(){
	var Spending = function () {
		var options = {
			series: [
				{
					name: 'Net Profit',
					data: [100, 300, 100, 400,100, 300, 100, 400],
					/* radius: 30,	 */
				},
			],
			chart: {
				type: 'line',
				height: 40,
				width: 100,
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
				width: 4,
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

		var chartBar1 = new ApexCharts(document.querySelector("#Spending"), options);
		chartBar1.render();

	}
	var Spending2 = function () {
		var options = {
			series: [
				{
					name: 'Net Profit',
					data: [100, 400, 100, 200,100, 300, 100, 300],
					/* radius: 30,	 */
				},
			],
			chart: {
				type: 'line',
				height: 40,
				width: 100,
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
				width: 4,
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

		var chartBar1 = new ApexCharts(document.querySelector("#Spending1"), options);
		chartBar1.render();

	}

	var Spending3 = function () {
		var options = {
			series: [
				{
					name: 'Net Profit',
					data: [200, 100, 400, 200,100, 300, 100, 300],
					/* radius: 30,	 */
				},
			],
			chart: {
				type: 'line',
				height: 40,
				width: 100,
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
				width: 4,
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

		var chartBar1 = new ApexCharts(document.querySelector("#Spending3"), options);
		chartBar1.render();

	}
	var Spending4 = function () {
		var options = {
			series: [
				{
					name: 'Net Profit',
					data: [200, 100, 100, 200,100, 100, 200, 400],
					/* radius: 30,	 */
				},
			],
			chart: {
				type: 'line',
				height: 40,
				width: 100,
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
				width: 4,
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

		var chartBar1 = new ApexCharts(document.querySelector("#Spending4"), options);
		chartBar1.render();

	}
	var Spending5 = function () {
		var options = {
			series: [
				{
					name: 'Net Profit',
					data: [100, 400, 300, 400,100, 100, 200, 400],
					/* radius: 30,	 */
				},
			],
			chart: {
				type: 'line',
				height: 40,
				width: 100,
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
				width: 4,
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

		var chartBar1 = new ApexCharts(document.querySelector("#Spending5"), options);
		chartBar1.render();

	}
	var Spending6 = function () {
		var options = {
			series: [
				{
					name: 'Net Profit',
					data: [100, 300, 100, 400,100, 300, 100, 400],
					/* radius: 30,	 */
				},
			],
			chart: {
				type: 'line',
				height: 40,
				width: 100,
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
				width: 4,
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

		var chartBar1 = new ApexCharts(document.querySelector("#Spending6"), options);
		chartBar1.render();

	}
	var Spending7 = function () {
		var options = {
			series: [
				{
					name: 'Net Profit',
					data: [100, 400, 100, 200,100, 300, 100, 300],
					/* radius: 30,	 */
				},
			],
			chart: {
				type: 'line',
				height: 40,
				width: 100,
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
				width: 4,
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

		var chartBar1 = new ApexCharts(document.querySelector("#Spending7"), options);
		chartBar1.render();

	}

	var Spending8 = function () {
		var options = {
			series: [
				{
					name: 'Net Profit',
					data: [200, 100, 400, 200,100, 300, 100, 300],
					/* radius: 30,	 */
				},
			],
			chart: {
				type: 'line',
				height: 40,
				width: 100,
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
				width: 4,
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

		var chartBar1 = new ApexCharts(document.querySelector("#Spending8"), options);
		chartBar1.render();

	}
	var Spending9 = function () {
		var options = {
			series: [
				{
					name: 'Net Profit',
					data: [200, 100, 100, 200,100, 100, 200, 400],
					/* radius: 30,	 */
				},
			],
			chart: {
				type: 'line',
				height: 40,
				width: 100,
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
				width: 4,
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

		var chartBar1 = new ApexCharts(document.querySelector("#Spending9"), options);
		chartBar1.render();

	}
	var Spending10 = function () {
		var options = {
			series: [
				{
					name: 'Net Profit',
					data: [100, 400, 300, 400,100, 100, 200, 400],
					/* radius: 30,	 */
				},
			],
			chart: {
				type: 'line',
				height: 40,
				width: 100,
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
				width: 4,
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

		var chartBar1 = new ApexCharts(document.querySelector("#Spending10"), options);
		chartBar1.render();

	}
	
	
	
	
	
	/* Function ============ */
	return {
		
		load:function(){
			Spending();
			Spending2();
			Spending3();
			Spending4();
			Spending5();
			Spending6();
			Spending7();
			Spending8();
			Spending9();
			Spending10();
			
		},
		
		resize:function(){
			
		}
	}
	
}();

jQuery(window).on('load',function(){
	setTimeout(function(){
		dzProfile.load();
	}, 1000); 
});

jQuery(window).on('resize',function () {
	setTimeout(function(){
		dzProfile.resize();
	}, 1000);
	
});

})(jQuery);
