/**
Core script to handle the entire theme and core functions
**/

function getUrlParams(dParam) {
	var dPageURL = window.location.search.substring(1),
		dURLVariables = dPageURL.split('&'),
		dParameterName,
		i;

	for (i = 0; i < dURLVariables.length; i++) {
		dParameterName = dURLVariables[i].split('=');

		if (dParameterName[0] === dParam) {
			return dParameterName[1] === undefined ? true : decodeURIComponent(dParameterName[1]);
		}
	}
}

var Tayang = function () {

	/* Search Bar ============ */
	var screenWidth = $(window).width();

	// var homeSearch = function() {}

	var handleSelectPicker = function () {
		if (jQuery('.default-select').length > 0) {
			jQuery('.default-select').selectpicker();
		}
		$('select').selectpicker();
	}

	var handleTheme = function () {
		$('#preloader').fadeOut(500);
		$('#main-wrapper').addClass('show');
	}

	var handleMetisMenu = function () {
		if (jQuery('#menu').length > 0) {
			$("#menu").metisMenu();
		}
		jQuery('.metismenu > .mm-active ').each(function () {
			if (!jQuery(this).children('ul').length > 0) {
				jQuery(this).addClass('active-no-child');
			}
		});
	}


	var handleAllChecked = function () {
		$("#checkAll, #checkAll4, #checkAll1, #checkAll2, #checkAll5").on('change', function () {
			$("td input:checkbox, .custom-checkbox input:checkbox").prop('checked', $(this).prop("checked"));
		});
		$(".checkAllInput").on('click', function () {
			jQuery(this).closest('.ItemsCheckboxSec').find('input[type="checkbox"]').prop('checked', true);
		});
		$(".unCheckAllInput").on('click', function () {
			jQuery(this).closest('.ItemsCheckboxSec').find('input[type="checkbox"]').prop('checked', false);
		});
	}

	var handleNavigation = function () {
		$(".nav-control").on('click', function () {

			$('#main-wrapper').toggleClass("menu-toggle");

			$(".hamburger").toggleClass("is-active");
		});
	}

	var handleCurrentActive = function () {
		for (var nk = window.location,
			o = $("ul#menu a").filter(function () {
				return this.href == nk;
			})
				.addClass("mm-active")
				.parent()
				.addClass("mm-active"); ;) {
			// console.log(o)
			if (!o.is("li")) break;
			o = o.parent()
				.addClass("mm-show")
				.parent()
				.addClass("mm-active");
		}
	}

	var handleCkEditor = function () {
		if (jQuery("#ckeditor").length > 0) {
			ClassicEditor
				.create(document.querySelector('#ckeditor'), {
					simpleUpload: {
						uploadUrl: 'ckeditor-upload.php',
					}
				})
				.then(editor => {
					window.editor = editor;
				})
				.catch(err => {
					console.error(err.stack);
				});
		}
	}
	var handleCustomFileInput = function () {
		$(".custom-file-input").on("change", function () {
			var fileName = $(this).val().split("\\").pop();
			$(this).siblings(".custom-file-label").addClass("selected").html(fileName);
		});
	}
	var handleDraggableCard = function () {
		if ($('.draggable-zone').length > 0) {
			var dzCardDraggable = function () {
				return {
					init: function () {
						var containers = document.querySelectorAll('.draggable-zone');

						if (containers.length === 0) {
							return false;
						}

						var swappable = new Sortable.default(containers, {
							draggable: '.draggable',
							handle: '.draggable.draggable-handle',
							mirror: {
								appendTo: 'body',
								constrainDimensions: true
							}
						});

						swappable.on('drag:stop', () => {
							setTimeout(function () {
								setBoxCount();
							}, 200);
						})
					}
				};
			}();

			jQuery(document).on('ready',function () {
				dzCardDraggable.init();
			});

			function setBoxCount() {
				var cardCount = 0;
				jQuery('.dropzoneContainer').each(function () {
					cardCount = jQuery(this).find('.draggable-handle').length;
					jQuery(this).find('.totalCount').html(cardCount);
				});
			}
		}
	}

	var handleMiniSidebar = function () {
		$("ul#menu>li").on('click', function () {
			const sidebarStyle = $('body').attr('data-sidebar-style');
			if (sidebarStyle === 'mini') {
				console.log($(this).find('ul'))
				$(this).find('ul').stop()
			}
		})
	}



	var handleDataAction = function () {
		$('a[data-action="collapse"]').on("click", function (i) {
			i.preventDefault(),
				$(this).closest(".card").find('[data-action="collapse"] i').toggleClass("mdi-arrow-down mdi-arrow-up"),
				$(this).closest(".card").children(".card-body").collapse("toggle");
		});

		$('a[data-action="expand"]').on("click", function (i) {
			i.preventDefault(),
				$(this).closest(".card").find('[data-action="expand"] i').toggleClass("icon-size-actual icon-size-fullscreen"),
				$(this).closest(".card").toggleClass("card-fullscreen");
		});



		$('[data-action="close"]').on("click", function () {
			$(this).closest(".card").removeClass().slideUp("fast");
		});

		$('[data-action="reload"]').on("click", function () {
			var e = $(this);
			e.parents(".card").addClass("card-load"),
				e.parents(".card").append('<div class="card-loader"><i class=" ti-reload rotate-refresh"></div>'),
				setTimeout(function () {
					e.parents(".card").children(".card-loader").remove(),
						e.parents(".card").removeClass("card-load")
				}, 2000)
		});
	}

	var handleHeaderHight = function () {
		const headerHight = $('.header').innerHeight();
		$(window).scroll(function () {
			if ($('body').attr('data-layout') === "horizontal" && $('body').attr('data-header-position') === "static" && $('body').attr('data-sidebar-position') === "fixed")
				$(this.window).scrollTop() >= headerHight ? $('.deznav').addClass('fixed') : $('.deznav').removeClass('fixed')
		});
	}



	var handleMenuTabs = function () {
		if (screenWidth <= 991) {
			jQuery('.menu-tabs .nav-link').on('click', function () {
				if (jQuery(this).hasClass('open')) {
					jQuery(this).removeClass('open');
					jQuery('.fixed-content-box').removeClass('active');
					jQuery('.hamburger').show();
				} else {
					jQuery('.menu-tabs .nav-link').removeClass('open');
					jQuery(this).addClass('open');
					jQuery('.fixed-content-box').addClass('active');
					jQuery('.hamburger').hide();
				}
				//jQuery('.fixed-content-box').toggleClass('active');
			});
			jQuery('.close-fixed-content').on('click', function () {
				jQuery('.fixed-content-box').removeClass('active');
				jQuery('.hamburger').removeClass('is-active');
				jQuery('#main-wrapper').removeClass('menu-toggle');
				jQuery('.hamburger').show();
			});
		}
	}

	var handleChatbox = function () {
		jQuery('.bell-link').on('click', function () {
			jQuery('.chatbox').addClass('active');
		});
		jQuery('.chatbox-close').on('click', function () {
			jQuery('.chatbox').removeClass('active');
		});
	}

	var handleBtnNumber = function () {
		$('.btn-number').on('click', function (e) {
			e.preventDefault();

			fieldName = $(this).attr('data-field');
			type = $(this).attr('data-type');
			var input = $("input[name='" + fieldName + "']");
			var currentVal = parseInt(input.val());
			if (!isNaN(currentVal)) {
				if (type == 'minus')
					input.val(currentVal - 1);
				else if (type == 'plus')
					input.val(currentVal + 1);
			} else {
				input.val(0);
			}
		});
	}

	var handledzChatUser = function () {
		jQuery('.dz-chat-user-box .dz-chat-user').on('click', function () {
			jQuery('.dz-chat-user-box').addClass('d-none');
			jQuery('.dz-chat-history-box').removeClass('d-none');
		});

		jQuery('.dz-chat-history-back').on('click', function () {
			jQuery('.dz-chat-user-box').removeClass('d-none');
			jQuery('.dz-chat-history-box').addClass('d-none');
		});

		jQuery('.dz-fullscreen').on('click', function () {
			jQuery('.dz-fullscreen').toggleClass('active');
		});
	}

	var handledzLoadMore = function () {
		$(".dz-load-more").on('click', function (e) {
			e.preventDefault();	//STOP default action
			$(this).append(' <i class="fa fa-refresh"></i>');

			var dzLoadMoreUrl = $(this).attr('rel');
			var dzLoadMoreId = $(this).attr('id');

			$.ajax({
				method: "POST",
				url: dzLoadMoreUrl,
				dataType: 'html',
				success: function (data) {
					$("#" + dzLoadMoreId + "Content").append(data);
					$('.dz-load-more i').remove();
				}
			})
		});
	}


	var handleheartBlast = function () {
		$(".heart").on("click", function () {
			$(this).toggleClass("heart-blast");
		});
	}

	var handleshowPass = function () {
		jQuery('.show-pass').on('click', function () {
			jQuery(this).toggleClass('active');
			if (jQuery('#dz-password').attr('type') == 'password') {
				jQuery('#dz-password').attr('type', 'text');
			} else if (jQuery('#dz-password').attr('type') == 'text') {
				jQuery('#dz-password').attr('type', 'password');
			}
		});
	}

	var handleLightgallery = function () {
		if (jQuery('#lightgallery').length > 0) {
			$('#lightgallery').lightGallery({
				loop: true,
				thumbnail: true,
				exThumbImage: 'data-exthumbimage'
			});
		}
		if (jQuery('#lightgallery2').length > 0) {
			$('#lightgallery2').lightGallery({
				loop: true,
				thumbnail: true,
				exThumbImage: 'data-exthumbimage'
			});
		}
	}

	var domoPanel = function () {
		if (jQuery('.dz-demo-content').length > 0) {
		}
		$('.dz-demo-trigger').on('click', function () {
			$('.dz-demo-panel').addClass('show');
		});
		$('.dz-demo-close, .bg-close').on('click', function () {
			$('.dz-demo-panel').removeClass('show');
		});

		$('.dz-demo-bx').on('click', function () {
			$('.dz-demo-bx').removeClass('demo-active');
			$(this).addClass('demo-active');
		});
	}
	var handleThemeMode = function () {
		if (jQuery(".dz-theme-mode").length > 0) {

			jQuery('.dz-theme-mode').on('click', function () {
				jQuery(this).toggleClass('active');

				if (jQuery(this).hasClass('active')) {
					jQuery('body').attr('data-theme-version', 'dark');
					setCookie('version', 'dark');
				} else {
					jQuery('body').attr('data-theme-version', 'light');
					setCookie('version', 'light');
				}
			});
			var version = getCookie('version');
			if (version != null) {
				jQuery('body').attr('data-theme-version', version);
			}
			jQuery('.dz-theme-mode').removeClass('active');

			jQuery(window).on('resize', function () {
				var version = getCookie('version');
				if (version != null) {
					jQuery('body').attr('data-theme-version', version);
				}
			})

			setTimeout(function () {
				if (jQuery('body').attr('data-theme-version') === "dark") {
					jQuery('.dz-theme-mode').addClass('active');
				}
			}, 1600)
		}
	}

	var handleDatetimepicker = function () {
		//    if(jQuery(".datepicker").length>0) {
		//        $('.datepicker').datetimepicker();
		//    }
		if (jQuery('.bt-datepicker').length > 0) {
			$(".bt-datepicker").datepicker({
				autoclose: true,
				todayHighlight: true
			}).datepicker('update', new Date());
		}
	}

	var handleMinHeight = function () {
		setTimeout(() => {
			if (
				$('body').attr('data-header-position') === "fixed"
				&&
				$('body').attr('data-layout') === "horizontal"
				&&
				$('body').attr('data-sidebar-position') === "fixed"
			) {
				$('.content-body').css("padding-top", ($('.deznav').height() + $('.header').height()) + 'px');
			} else if (
				$('body').attr('data-header-position') === "fixed"
				&&
				$('body').attr('data-layout') === "horizontal"
				&&
				$('body').attr('data-sidebar-position') === "static"
			) {
				$('.content-body').css("padding-top", $('.header').height() + "px");
			} else if (
				$('body').attr('data-header-position') === "static"
				&&
				$('body').attr('data-layout') === "horizontal"
				&&
				$('body').attr('data-sidebar-position') === "fixed"
			) {
				$('.content-body').css("padding-top", "0px");
			} else {
				$('.content-body').css("padding-top", "");
			}
		}, 400);

	}
	var priceslider = function () {
		if ($("#slider-tooltips-product").length > 0) {
			var tooltipSlider = document.getElementById('slider-tooltips-product');

			var formatForSlider = {
				from: function (formattedValue) {
					return Number(formattedValue);
				},
				to: function (numericValue) {
					return Math.round(numericValue);
				}
			};

			noUiSlider.create(tooltipSlider, {
				start: [40, 346],
				connect: true,
				format: formatForSlider,
				tooltips: [wNumb({ decimals: 1 }), true],
				range: {
					'min': 0,
					'max': 400
				}
			});
			var formatValues = [
				document.getElementById('slider-margin-value-min'),
				document.getElementById('slider-margin-value-max')
			];
			tooltipSlider.noUiSlider.on('update', function (values, handle, unencoded) {
				formatValues[0].innerHTML = "" + "$" + values[0];
				formatValues[1].innerHTML = "" + "$" + values[1];
			});
		}
	}

	var setCurrentYear = function () {
		const currentDate = new Date();
		let currentYear = currentDate.getFullYear();
		let elements = document.getElementsByClassName('current-year');

		for (const element of elements) {
			element.innerHTML = currentYear;
		}
	}
	var handleMagnifyGallery = function () {
		const imageSelector = $('.DZoomImage');
		imageSelector.on('mousemove', function (t) {
			let e = $(this).offset();
			var i = (t.pageX - e.left) / $(this).width() * 100 <= 100 ? (t.pageX - e.left) / $(this).width() * 100 : 100;
			var c = (t.pageY - e.top) / $(this).height() * 100 <= 100 ? (t.pageY - e.top) / $(this).height() * 100 : 100;

			$(this).find('img').css("transform-origin", i + "% " + c + "%");
		})
		imageSelector.on('mouseenter', function (t) {
			let n = $(this).find('img');
			n.css("cursor", "pointer"),
				/* n.css("width", $(this).width() + "px"), 
				n.css("height", $(this).height() + "px"), */
				n.css("transition", "0.1s"),
				n.css("transform", "scale(" + 1.5 + ")"),
				$(this).find('.mfp-link i').css({ opacity: 1, zIndex: 1 })
		});
		imageSelector.on('mouseleave', function (t) {
			let n = $(this).find('img');
			n.css("transition", "0.1s"), n.css("transform", "scale(1)")
			$(this).find('.mfp-link i').css({ opacity: 0, zIndex: 1 })
		});
	}
	/* Website Launch Date */ 
	var WebsiteLaunchDate = new Date();
	var monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
	WebsiteLaunchDate.setMonth(WebsiteLaunchDate.getMonth() + 1);
	WebsiteLaunchDate =  WebsiteLaunchDate.getDate() + " " + monthNames[WebsiteLaunchDate.getMonth()] + " " + WebsiteLaunchDate.getFullYear();
	/* Website Launch Date END */ 
	
	/* Countdown ============ */
	var handleCountDown = function(WebsiteLaunchDate){
		/* Time Countr Down Js */
		if($(".countdown").length)
		{
			$('.countdown').countdown({date: WebsiteLaunchDate+' 23:5'}, function() {
				$('.countdown').text('we are live');
			});
		}
		/* Time Countr Down Js End */
	}
	var handlefollowbtn = function() {		

		$(".btn-follow").on('click',function() {
			var $btn = $(this);
			
			if ($btn.text() == "Follow") {
				$btn.text("Following");
				$btn.css({
					"border-color": "#F3F5F8",
					"background-color": "#F3F5F8",
					"color": "black"
				});
			} else {
				$btn.text("Follow");
				$btn.css({
					"border-color": "#2aa3e7",
					"background-color": "#2aa3e7",
					"color": "#fff"
				});
			}
		});
	}

	/* Function ============ */
	return {
		init: function () {
			handleTheme();
			handleMetisMenu();
			handleAllChecked();
			handleNavigation();
			handleCurrentActive();
			handleCustomFileInput();
			handleDraggableCard();
			handleMiniSidebar();
			handleDataAction();
			handleHeaderHight();
			handleMenuTabs();
			handleChatbox();
			handleBtnNumber();
			handledzChatUser();
			handledzLoadMore();
			handleheartBlast();
			handleshowPass();
			handleLightgallery();
			handleCkEditor();
			domoPanel();
			handleSelectPicker();
			handleThemeMode();
			handleDatetimepicker();
			handleMinHeight();
			setCurrentYear();
			priceslider();
			handleMagnifyGallery();
			handleCountDown(WebsiteLaunchDate);
			handlefollowbtn();
		},


		load: function () {
			handleSelectPicker();
			handleTheme();		},

		resize: function () {


		}
	}

}();

/* Document.ready Start */
jQuery(document).ready(function () {
	'use strict';
	Tayang.init();

});
/* Document.ready END */

/* Window Load START */
jQuery(window).on('load', function () {
	'use strict';
	Tayang.load();

});
/*  Window Load END */

/* Window Resize START */
jQuery(window).on('resize', function () {
	'use strict';
	Tayang.resize();
});
/*  Window Resize END */