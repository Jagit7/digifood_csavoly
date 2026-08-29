(function ($) {
    /* "use strict" */


    var dzChartlist = function () {

        var screenWidth = $(window).width();

        var inputSelectSlider = function () {
            //html5 input element
            //var select = document.getElementById('input-select');
            // Append the option elements


            var html5Slider = document.getElementById('html5');
            noUiSlider.create(html5Slider, {

                connect: true,
                range: {
                    'min': 0,
                    '10%': 10,
                    '20%': 20,
                    '30%': 30,
                    // Nope, 40 is no fun.
                    '50%': 50,
                    '60%': 60,
                    '70%': 70,
                    // I never liked 80.
                    '90%': 90,
                    'max': 100
                },
                snap: true,
                start: [0, 100]
            });

            var inputNumber = document.getElementById('input-number');
            html5Slider.noUiSlider.on('update', function (values, handle) {

                var value = values[handle];

                if (handle) {
                    inputNumber.value = value;
                }
            });

            inputNumber.addEventListener('change', function () {
                html5Slider.noUiSlider.set([null, this.value]);
            });
            //html5 input element

        }
        //Number formatting
        // Initialize the slider
        var sliderFormat = document.getElementById('slider-format');
        noUiSlider.create(sliderFormat, {
            start: [500], // Initialize with 500 (without $)
            step: 50,
            connect: [true, false],
            range: {
                'min': [0],
                'max': [1000]
            },
            ariaFormat: wNumb({
                decimals: 0,
                prefix: '$'
            }),
            format: wNumb({
                decimals: 0,
                thousand: '.',
                prefix: '$'
            })
        });

        // Update the input field when the slider value changes
        var inputFormat = document.getElementById('input-format');
        sliderFormat.noUiSlider.on('update', function (values, handle) {
            inputFormat.value = values[handle];
        });

        // Update the slider when the input field value changes
        inputFormat.addEventListener('change', function () {
            var value = this.value.replace('$', ''); // Remove $ sign for correct parsing
            sliderFormat.noUiSlider.set(value);
        });
        //Number formatting ^

        /* Function ============ */
        return {
            init: function () {
                inputSelectSlider();
            },


            load: function () {
                
            },

            resize: function () {

            }
        }

    }();

    jQuery(window).on('load', function () {
        dzChartlist.load();

    });

    jQuery(window).on('resize', function () {


    });

})(jQuery);