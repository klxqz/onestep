(function ($) {
    "use strict";
    $.onestep = {
        timer_id: null,
        jqxhr: null,
        reload_steps: [],
        options: {},
        init: function (options) {
            this.options = options;
            this.initContactinfo();
            this.initShipping();
            this.initPayment();
            this.initConfirmation();
            this.initCart();
            this.auth();
            if (this.options.validate) {
                this.valide();
            }
            if (this.options.submit) {
                this.errorScroll();
            }
            this.initKeyUp();
            this.reloadSteps(['confirmation']);
        },
        errorScroll: function () {
            if ($('.checkout-step .error:visible').length) {
                var destination = $('.checkout-step .error:visible').offset().top;
                $('html,body').animate({
                    scrollTop: destination
                }, 1100);
            }
        },
        valide: function () {
            $("form.checkout-form").validate({
                rules: {
                    terms: {
                        required: true
                    }
                },
                messages: {
                    terms: "Это поле обязательное для заполнения"
                }
            });
            $('form.checkout-form .wa-required input:visible').each(function () {
                $(this).rules('add', {
                    required: true,
                    messages: {
                        required: "Это поле обязательное для заполнения"
                    }
                });
            });
        },
        initContactinfo: function () {
            this.createUser();
            var self = this;
            $('#checkout-contact-form input:not([type="checkbox"]),#checkout-contact-form select').change(function () {
                if ($(this).closest('#create-user-div').length || $(this).attr('name') == 'customer[phone]' || $(this).attr('name') == 'customer[email]') {
                    return false;
                }
                self.reloadSteps(['shipping', 'payment', 'confirmation']);
            });
        },
        initShipping: function () {
            this.shippingExternalMethods();
            this.shippingOptions();
            this.addressChange();
            this.shippingRates();

            $(".checkout-options .wa-address input,.checkout-options .wa-address select").attr('disabled', true);
            $(".checkout-options [name=shipping_id]:checked").closest('li').find('.wa-address input,.wa-address select').removeAttr('disabled');


            var self = this;
            $('input[name=shipping_id],.shipping-rates').change(function () {
                self.reloadSteps(['payment', 'confirmation']);
            });
        },
        initPayment: function () {
            this.paymentOptions();
        },
        initConfirmation: function () {

        },
        updateCheckoutCode: function (response) {
            if ($('.checkout-form .step-contactinfo [name=checkout_code]').next('script').length) {
                $('.checkout-form .step-contactinfo [name=checkout_code]').next('script').replaceWith('<script type="text/javascript">' + $(response).find('[name=checkout_code]').next('script').html() + '</script>');
            }
        },
        addressChange: function () {
            var self = this;
            $(".wa-address").find('input,select').change(function () {
                if ($(this).data('ignore')) {
                    return true;
                }
                var name = $(this).attr('name');
                var value = $(this).val();
                var match = name.match(/customer_(.+)\[address.shipping\]\[(.+)\]/);

                if (match[2] !== undefined) {
                    $('ul.checkout-options li input[name=shipping_id]').each(function () {
                        var shipping_id = $(this).val();
                        if (shipping_id == match[1]) {
                            return;
                        }
                        var field = $('[name="customer_' + shipping_id + '[address.shipping][' + match[2] + ']"]');

                        if (field.length) {
                            switch (field.get(0).tagName) {
                                case 'SELECT':
                                    field.find('option[value="' + value + '"]').attr("selected", true);
                                    break;
                                case 'INPUT':
                                    field.val(value);
                                    break;
                            }
                        }
                    });
                }
                /*
                 
                 var loaded_flag = false;
                 setTimeout(function () {
                 if (!loaded_flag && !$(".shipping-" + shipping_id + " .price .loading").length) {
                 $(".shipping-" + shipping_id + " .price").append(' <i class="icon16 loading"></i>');
                 }
                 }, 300);*/

                /*
                 $.post(self.options.shipping_url, $("form").serialize(), function (response) {
                 loaded_flag = true;
                 self.responseCallback(shipping_id, response.data);
                 self.reloadSteps(['payment', 'confirmation']);
                 }, "json");*/

                var shipping_methods = [];
                var shipping_ids = [];
                $('ul.checkout-options li input[name=shipping_id]').each(function () {
                    shipping_methods.push($(this).val());
                    shipping_ids.push('shipping_ids[]=' + $(this).val());
                    if (!$(this).closest('li').find('.price .loading').length) {
                        $(this).closest('li').find('.price').append(' <i class="icon16 loading"></i>');
                    }
                });


                $.post(self.options.shipping_url, $("form").serialize() + '&' + shipping_ids.join('&') + '&html=1', function (response) {
                    for (var shipping_id in response.data) {
                        self.responseCallback(shipping_id, response.data[shipping_id]);
                    }
                    self.reloadSteps(['payment', 'confirmation']);
                }, "json").fail(function (response) {
                    console.log(response);
                    for (var i in shipping_methods) {
                        var shipping_id = self.options.shipping_methods[i];
                        self.responseCallback(shipping_id, 'Ошибка получения данных');
                    }
                    self.reloadSteps(['payment', 'confirmation']);
                });


            });
        },
        shippingRates: function () {
            $(".checkout-options").on('change', "select.shipping-rates", function (e, not_check) {
                var opt = $(this).children('option:selected');
                var li = $(this).closest('li');
                li.find('.price').html(opt.data('rate'));
                if (!not_check) {
                    li.find('input:radio').attr('checked', 'checked');
                }
                li.find('.est_delivery').html(opt.data('est_delivery'));
                if (opt.data('est_delivery')) {
                    li.find('.est_delivery').parent().show();
                } else {
                    li.find('.est_delivery').parent().hide();
                }
                if (opt.data('comment')) {
                    li.find('.comment').html('<br>' + opt.data('comment')).show();
                } else {
                    li.find('.comment').empty().hide();
                }
            });
        },
        shippingOptions: function () {
            $(".checkout-options input:radio").change(function () {
                if ($(this).is(':checked') && !$(this).data('ignore')) {
                    $(".checkout-options .wa-form").hide();
                    $(".checkout-options .wa-form").find('input,select').attr('disabled', true);
                    $(this).closest('li').find('.wa-form').show();
                    $(this).closest('li').find('.wa-form').find('input,select').removeAttr('disabled');
                    if ($(this).data('changed')) {
                        $(this).closest('li').find('.wa-form').find('input,select').data('ignore', 1).change().removeData('ignore');
                        $(this).removeData('changed');
                    }
                }
            });
        },
        paymentOptions: function () {
            $(".checkout-options.payment input:radio").change(function () {
                if ($(this).is(':checked')) {
                    $(".checkout-options.payment .wa-form").hide();
                    $(this).closest('li').find('.wa-form').show();
                }
            });
        },
        shippingExternalMethods: function () {
            var self = this;
            var shipping_external_methods = [];
            $('ul.checkout-options li .price .loading').each(function () {
                shipping_external_methods.push($(this).closest('li').find('input[name=shipping_id]').val());
            });
            if (shipping_external_methods.length) {
                $.get(this.options.shipping_url, {
                    shipping_id: shipping_external_methods
                }, function (response) {
                    for (var shipping_id in response.data) {
                        self.responseCallback(shipping_id, response.data[shipping_id]);
                    }
                    self.reloadSteps(['payment', 'confirmation']);
                }, "json").fail(function (response) {
                    console.log(response);
                    for (var i in shipping_external_methods) {
                        var shipping_id = self.options.external_methods[i];
                        self.responseCallback(shipping_id, 'Ошибка получения данных');
                    }
                    self.reloadSteps(['payment', 'confirmation']);
                });
            }
        },
        createUser: function () {
            var e = $('input[name="customer[email]"]');
            if (e.length) {
                e.on('keyup', function () {
                    if ($("#create-user-div").is(':visible')) {
                        $('#create-user-div input[name="login"]').val($(this).val());
                    }
                });
                $('#create-user-div input[name="login"]').on('keyup', function () {
                    e.val($(this).val());
                })
            }
            $("#create-user").change(function () {
                if ($(this).is(':checked')) {
                    $("#create-user-div").show().find('input').removeAttr('disabled');
                    var l = $(this).closest('form').find('input[name="customer[email]"]');
                    if (l.length && l.val()) {
                        $('#create-user-div input[name="login"]').val(l.val());
                    }
                } else {
                    $("#create-user-div").hide().find('input').attr('disabled', 'disabled').val('');
                }
            }).change();
        },
        auth: function () {
            $("#login-form input").attr('disabled', 'disabled');
            $("input[name='user_type']").change(function () {
                if ($("input[name='user_type']:checked").val() == '1') {
                    $("#login-form input").removeAttr('disabled');
                    $(".checkout-step").hide();
                    $(this).closest('.checkout-step').show();
                    $(this).closest('div.auth').next(".checkout-step-content").hide();
                    $("#create-user-div").find('input').attr('disabled', 'disabled');
                    $("input[type=submit]:last").hide();
                    $("#login-form").show();
                } else {
                    $("#login-form input").attr('disabled', 'disabled');
                    $(".checkout-step").show();
                    $("#login-form").hide();
                    $("#create-user-div").find('input').removeAttr('disabled');
                    $(this).closest('div.auth').next(".checkout-step-content").show();
                    $("input[type=submit]:last").show();
                }
            });
            $("input[name='user_type']").change();
        },
        initCart: function () {
            var self = this;
            var self = this;
            $(".onestep-cart .services input:checkbox").click(function () {
                var obj = $('.onestep-cart select[name="service_variant[' + $(this).closest('tr').data('id') + '][' + $(this).val() + ']"]');
                if (obj.length) {
                    if ($(this).is(':checked')) {
                        obj.removeAttr('disabled');
                    } else {
                        obj.attr('disabled', 'disabled');
                    }
                }
            });
            $(".onestep-cart .cart a.delete").click(function () {
                var tr = $(this).closest('tr');
                $.post('delete/', {html: 1, id: tr.data('id')}, function (response) {
                    if (response.data.count == 0) {
                        location.reload();
                    }
                    tr.remove();
                    self.updateCart(response.data);
                }, "json");
                return false;
            });
            $(".onestep-cart .cart input.qty").change(function () {
                var that = $(this);
                if (that.val() > 0) {
                    var tr = that.closest('tr');
                    if (that.val()) {
                        $.post('save/', {html: 1, id: tr.data('id'), quantity: that.val()}, function (response) {
                            tr.find('.item-total').html(response.data.item_total);
                            if (response.data.q) {
                                that.val(response.data.q);
                            }
                            if (response.data.error) {
                                alert(response.data.error);
                            } else {
                                that.removeClass('error');
                            }
                            self.updateCart(response.data);
                        }, "json");
                    }
                } else {
                    that.val(1);
                }
            });
            $(".onestep-cart .cart .services input:checkbox").change(function () {
                var div = $(this).closest('div');
                var tr = $(this).closest('tr');
                if ($(this).is(':checked')) {
                    var parent_id = $(this).closest('tr').data('id')
                    var data = {html: 1, parent_id: parent_id, service_id: $(this).val()};
                    var variants = $('select[name="service_variant[' + parent_id + '][' + $(this).val() + ']"]');
                    if (variants.length) {
                        data['service_variant_id'] = variants.val();
                    }
                    $.post('add/', data, function (response) {
                        div.data('id', response.data.id);
                        tr.find('.item-total').html(response.data.item_total);
                        self.updateCart(response.data);
                    }, "json");
                } else {
                    $.post('delete/', {html: 1, id: div.data('id')}, function (response) {
                        div.data('id', null);
                        tr.find('.item-total').html(response.data.item_total);
                        self.updateCart(response.data);
                    }, "json");
                }
            });
            $(".onestep-cart .cart .services select").change(function () {
                var tr = $(this).closest('tr');
                $.post('save/', {html: 1, id: $(this).closest('div').data('id'), 'service_variant_id': $(this).val()}, function (response) {
                    tr.find('.item-total').html(response.data.item_total);
                    self.updateCart(response.data);
                }, "json");
            });
            $(".onestep-cart #cancel-affiliate").click(function () {
                $(this).closest('form').append('<input type="hidden" name="use_affiliate" value="0">').submit();
                return false;
            })
        },
        updateCart: function (data)
        {
            $(".onestep-cart .cart-total").html(data.total);
            if (data.discount_numeric) {
                $(".onestep-cart .cart-discount").closest('tr').show();
            }
            $(".onestep-cart .cart-discount").html('&minus; ' + data.discount);
            if (data.add_affiliate_bonus) {
                $(".onestep-cart .affiliate").show().html(data.add_affiliate_bonus);
            } else {
                $(".onestep-cart .affiliate").hide();
            }
            this.reloadSteps(['contactinfo', 'shipping', 'payment', 'confirmation']);
        },
        initKeyUp: function () {
            /*
             var self = this;
             $('.checkout-form input').off('keyup').on('keyup', function () {
             self.reloadAbort();
             self.reloadSteps();
             });*/
        },
        reloadAbort: function () {
            //clearTimeout(this.timer_id);
            if (this.jqxhr !== null) {
                this.jqxhr.abort();
            }
            $('.checkout-step h2 i.loading').remove();
            $('.checkout-form .please-wait').remove();
        },
        reloadSteps: function (steps) {
            if (steps !== undefined) {
                for (var i in steps) {
                    if (this.reload_steps.indexOf(steps[i]) === -1) {
                        this.reload_steps.push(steps[i]);
                    }
                }
            }
            steps = this.reload_steps;
            if (!steps.length) {
                return;
            }

            this.reloadAbort();

            for (var i in steps) {
                var step = steps[i];
                $('.step-' + step + ' h2').append('<i class="icon16 loading"></i>');
            }
            var f = $("form.checkout-form");
            $(f).find('[name=confirmation]').attr('disabled', 'disabled');
            $(f).find('#checkout-btn').attr('disabled', 'disabled');
            $(f).find('#checkout-btn').before('<span class="please-wait"><i class="icon16 loading"></i>Пожалуйста, подождите...&nbsp;</span>');
            var self = this;

            //this.timer_id = setTimeout(function () {
            self.jqxhr = $.ajax({
                type: 'POST',
                url: window.location,
                dataType: 'html',
                data: f.serialize(),
                success: function (response) {
                    for (var i in steps) {
                        var step = steps[i];
                        var html = $(response).find('.step-' + step).html();
                        $('.step-' + step).html(html);
                        switch (step) {
                            case 'contactinfo':
                                self.initContactinfo();
                                break;
                            case 'shipping':
                                self.initShipping();
                                break;
                            case 'payment':
                                self.initPayment();
                                break;
                            case 'confirmation':
                                self.initConfirmation();
                                break;
                        }
                    }
                    self.updateCheckoutCode(response);
                    $("form.checkout-form").find('[name=confirmation]').removeAttr('disabled');
                    $("form.checkout-form").find('#checkout-btn').removeAttr('disabled');
                    self.jqxhr = null;
                    self.reload_steps = [];
                    self.initKeyUp();
                }
            });
            //}, 1000);
        },
        responseCallback: function (shipping_id, data) {
            var name = 'rate_id[' + shipping_id + ']';
            if (typeof (data) != 'string') {
                $(".shipping-" + shipping_id + ' input:radio').removeAttr('disabled');
            }
            if (typeof (data) == 'string') {
                $(".shipping-" + shipping_id + ' input[name="' + name + '"]').remove();
                $(".shipping-" + shipping_id + ' select[name="' + name + '"]').remove();
                var el = $(".shipping-" + shipping_id).find('.rate');
                if (el.hasClass('error')) {
                    el.find('em').html(data);
                } else {
                    el.find('.price, .hint').hide();
                    el.addClass('error').append($('<em class="shipping-error"></em>').html(data));
                }
            } else if (data.length > 1) {
                $(".shipping-" + shipping_id + ' input[name="' + name + '"]').remove();
                var select = $(".shipping-" + shipping_id + ' select[name="' + name + '"]');
                var html_select = $('<select class="shipping-rates" name="' + name + '"></select>');
                for (var i = 0; i < data.length; i++) {
                    var r = data[i];
                    var option = $('<option data-rate="' + r.rate + '" data-comment="' + (r.comment || '') + '" data-est_delivery="' + (r.est_delivery || '') + '" value="' + r.id + '">' + r.name + ' (' + r.rate + ')</option>');
                    if (r.rate_html !== undefined) {
                        option.data('rate', r.rate_html);
                    } else {
                        option.data('rate', r.rate);
                    }
                    html_select.append(option);
                }

                if (select.length) {
                    var selected = select.val();
                    select.remove();
                } else {
                    var selected = false;
                }
                select = html_select;
                $(".shipping-" + shipping_id + " h3").append(html_select);
                if (selected) {
                    select.val(selected);
                }
                select.trigger('change', 1);
                $(".shipping-" + shipping_id).find('.rate').removeClass('error').find('.price').show();
                $(".shipping-" + shipping_id).find('.rate em.shipping-error').remove();
            } else {
                $(".shipping-" + shipping_id + ' select[name="' + name + '"]').remove();
                var input = $(".shipping-" + shipping_id + ' input[name="' + name + '"]');
                if (input.length) {
                    input.val(data[0].id);
                } else {
                    $(".shipping-" + shipping_id + " h3").append('<input type="hidden" name="' + name + '" value="' + data[0].id + '">');
                }
                if (data[0].rate_html !== undefined) {
                    $(".shipping-" + shipping_id + " .price").html(data[0].rate_html);
                } else {
                    $(".shipping-" + shipping_id + " .price").html(data[0].rate);
                }
                $(".shipping-" + shipping_id + " .est_delivery").html(data[0].est_delivery);
                $(".shipping-" + shipping_id).find('.rate').removeClass('error').find('.price').show();
                if (data[0].est_delivery) {
                    $(".shipping-" + shipping_id + " .est_delivery").parent().show();
                } else {
                    $(".shipping-" + shipping_id + " .est_delivery").parent().hide();
                }
                if (data[0].comment) {
                    $(".shipping-" + shipping_id + " .comment").html('<br>' + data[0].comment).show();
                } else {
                    $(".shipping-" + shipping_id + " .comment").hide();
                }
                $(".shipping-" + shipping_id).find('.rate em.shipping-error').remove();
            }
        }
    };
})(jQuery);