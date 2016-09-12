(function ($) {
    $.onestep_settings = {
        options: {},
        init: function (options) {
            this.options = options;
            this.initButtons();
            this.initRouteSelector();
            this.initSave();
            return this;
        },
        initSave: function () {
            $(document).off('submit', '#plugins-settings-form_').on('submit', '#plugins-settings-form_', function () {
                $.plugins.saveHandlerAjax('#plugins-settings-form_');
                return false;
            });
        },
        initButtons: function () {
            $('#ibutton-status').iButton({
                labelOn: "Вкл", labelOff: "Выкл"
            }).change(function () {
                var self = $(this);
                var enabled = self.is(':checked');
                if (enabled) {
                    self.closest('.field-group').siblings().show(200);
                } else {
                    self.closest('.field-group').siblings().hide(200);
                }
                var f = $("#plugins-settings-form_");
                $.post(f.attr('action'), f.serialize());
            });
            $(document).on('click', '.helper-link', function () {
                $(this).closest('.value').next('.help-content').slideToggle('slow');
                $(this).find('i.icon10').toggleClass('darr-tiny').toggleClass('uarr-tiny');
                return false;
            });


        },
        initRouteSelector: function () {
            var templates = this.options.templates;
            $('#route-selector').change(function () {
                var self = $(this);
                var loading = $('<i class="icon16 loading"></i>');
                $(this).attr('disabled', true);
                $(this).after(loading);
                $('.route-container').find('input,select,textarea').attr('disabled', true);
                $('.route-container').slideUp('slow');
                $.get('?plugin=onestep&module=settings&action=route&route_hash=' + $(this).val(), function (response) {
                    $('.route-container').html(response);
                    loading.remove();
                    self.removeAttr('disabled');
                    $('.route-container').slideDown('slow');

                    $('.route-container .ibutton').iButton({
                        labelOn: "Вкл",
                        labelOff: "Выкл",
                        className: 'mini'
                    });

                    for (var i = 0; i < templates.length; i++) {
                        CodeMirror.fromTextArea(document.getElementById(templates[i].id), {
                            mode: "text/" + templates[i].mode,
                            tabMode: "indent",
                            height: "dynamic",
                            lineWrapping: true
                        });
                    }
                    $.onestep_settings.initSave();


                    $('.template-block').hide();
                    $('.edit-template').click(function () {
                        $(this).closest('.field').find('.template-block').slideToggle('slow');
                        return false;
                    });
                    $('.templates-block').hide();
                    $('.edit-templates').click(function () {
                        $(this).closest('.field-group').find('.templates-block').slideToggle('slow');
                        return false;
                    });

                });
                return false;
            }).change();
        }
    };
})(jQuery);
