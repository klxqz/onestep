$(function () {

    function updateCart(data)
    {
        $(".cart-total").html(data.total);
        if (data.discount_numeric) {
            $(".cart-discount").closest('tr').show();
        }
        $(".cart-discount").html('&minus; ' + data.discount);
        if (data.add_affiliate_bonus) {
            $(".affiliate").show().html(data.add_affiliate_bonus);
        } else {
            $(".affiliate").hide();
        }
    }

// add to cart block: services
    $(".services input:checkbox").click(function () {
        var obj = $('select[name="service_variant[' + $(this).closest('tr').data('id') + '][' + $(this).val() + ']"]');
        if (obj.length) {
            if ($(this).is(':checked')) {
                obj.removeAttr('disabled');
            } else {
                obj.attr('disabled', 'disabled');
            }
        }
    });
    $(".cart a.delete").click(function () {
        var tr = $(this).closest('tr');
        $.post('delete/', {html: 1, id: tr.data('id')}, function (response) {
            if (response.data.count == 0) {
                location.reload();
            }
            tr.remove();
            updateCart(response.data);
        }, "json");
        return false;
    });
    $(".cart input.qty").change(function () {
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
                    updateCart(response.data);
                }, "json");
            }
        } else {
            that.val(1);
        }
    });
    $(".cart .services input:checkbox").change(function () {
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
                updateCart(response.data);
            }, "json");
        } else {
            $.post('delete/', {html: 1, id: div.data('id')}, function (response) {
                div.data('id', null);
                tr.find('.item-total').html(response.data.item_total);
                updateCart(response.data);
            }, "json");
        }
    });
    $(".cart .services select").change(function () {
        var tr = $(this).closest('tr');
        $.post('save/', {html: 1, id: $(this).closest('div').data('id'), 'service_variant_id': $(this).val()}, function (response) {
            tr.find('.item-total').html(response.data.item_total);
            updateCart(response.data);
        }, "json");
    });
    $("#cancel-affiliate").click(function () {
        $(this).closest('form').append('<input type="hidden" name="use_affiliate" value="0">').submit();
        return false;
    })
});
(function ($) {
    "use strict";
    $.onestep = {
        options: {},
        init: function (options) {
            this.options = options;
            this.syncAddresses();
            this.formChange();
            this.formSubmit();
            this.checkMinOrder();
            this.initContactinfo();
            this.initShipping();
            this.initPayment();
            this.initConfirmation();
            /*this.setFirstMethod();*/
            if (this.options.validate) {
                this.valide();
            }

            if (this.options.submit) {
                this.errorScroll();
            }
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
        syncAddresses: function () {
            var self = this;
            var input_address_fields = ['street', 'city', 'zip', 'region', 'country'];
            for (var i in input_address_fields) {
                var input_address_field = input_address_fields[i];
                var selectors = [];
                selectors.push('input[name="customer[address.shipping][' + input_address_field + ']"]');
                selectors.push('select[name="customer[address.shipping][' + input_address_field + ']"]');
                for (var j in this.options.shipping_methods_ids) {
                    var shipping_method_id = this.options.shipping_methods_ids[j];
                    selectors.push('input[name="customer_' + shipping_method_id + '[address.shipping][' + input_address_field + ']"]');
                    selectors.push('select[name="customer_' + shipping_method_id + '[address.shipping][' + input_address_field + ']"]');
                }

                var text_selector = selectors.join(',');
                $('.checkout-form').on('change', text_selector, function () {
                    var value = $(this).val();
                    var selector = self.getSelector($(this).attr('name'));
                    $(selector).each(function () {
                        var tag = $(this).get(0).tagName;
                        switch (tag) {
                            case 'SELECT':
                                $(this).find('option[value="' + value + '"]').attr("selected", "selected");
                                break;
                            case 'INPUT':
                                $(this).val(value);
                                break;
                        }
                    });
                });
            }

        },
        getSelector: function (name) {
            var input_address_fields = ['street', 'city', 'zip', 'region', 'country'];
            for (var i in input_address_fields) {
                var input_address_field = input_address_fields[i];
                if (name.indexOf('[' + input_address_field + ']') != -1) {
                    var selectors = [];
                    selectors.push('input[name="customer[address.shipping][' + input_address_field + ']"]');
                    selectors.push('select[name="customer[address.shipping][' + input_address_field + ']"]');
                    for (var j in this.options.shipping_methods_ids) {
                        var shipping_method_id = this.options.shipping_methods_ids[j];
                        selectors.push('input[name="customer_' + shipping_method_id + '[address.shipping][' + input_address_field + ']"]');
                        selectors.push('select[name="customer_' + shipping_method_id + '[address.shipping][' + input_address_field + ']"]');
                    }

                    var text_selector = selectors.join(',');
                    return text_selector;
                }
            }
        },
        setFirstMethod: function () {
            if ($("form.checkout-form").find('input[name="shipping_id"]').length && !$("form.checkout-form").find('input[name="shipping_id"]:checked').not(':disabled').length) {
                $("form.checkout-form").find('input[name="shipping_id"]:first').click();
            }
            if ($("form.checkout-form").find('input[name="payment_id"]').length && !$("form.checkout-form").find('input[name="payment_id"]:checked').not(':disabled').length) {
                $("form.checkout-form").find('input[name="payment_id"]:first').click();
            }
        },
        checkMinOrder: function () {
            var self = this;
            setInterval(function () {
                if (self.options.total == $('.cart-total').html()) {
                    return false;
                }
                for (var i in self.options.steps) {
                    $('.step-' + self.options.steps[i] + ' h2 span.loading').html('<i class="icon16 loading"></i>');
                }
                $("form.checkout-form").find('[name=confirmation]').attr('disabled', 'disabled');
                $("form.checkout-form").find('[type=submit]').attr('disabled', 'disabled');
                $.post(window.location, $("form.checkout-form").serialize() + '&' + $('.onestep-cart-form').serialize(), function (response) {
                    for (var i in self.options.steps) {
                        var step = self.options.steps[i];
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
                });
                $.post(self.options.check_url, function (response) {
                    if (response.data.check) {
                        $('.checkout').show();
                        $('.onestep_min_summ').hide();
                    } else {
                        $('.checkout').hide();
                        $('.onestep_min_summ').show();
                    }
                }, 'json');
                self.options.total = $('.cart-total').html();
            }, 500);
        },
        formChange: function () {
            var self = this;
            $("form.checkout-form").on('change', 'input:not([name="user_type"],[name="login"],[name="password"],#create-user,[type="checkbox"]),select', function () {
                var f = $(this).closest("form.checkout-form");
                var cur_step = $(this).closest('.checkout-step').data('step');
                var j = self.options.steps.indexOf(cur_step);
                $(f).find('[name=confirmation]').attr('disabled', 'disabled');
                $(f).find('[type=submit]').attr('disabled', 'disabled');
                $.post(f.attr('action') || window.location, f.serialize() + '&' + $('.onestep-cart-form').serialize(), function (response) {
                    var j = self.options.steps.indexOf(cur_step);
                    for (var i in self.options.steps) {
                        if (i > j) {
                            var step = self.options.steps[i];
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
                    }
                    $("form.checkout-form").find('[name=confirmation]').removeAttr('disabled');
                    $("form.checkout-form").find('[type=submit]').removeAttr('disabled');
                });
                for (var i in self.options.steps) {
                    if (i > j) {
                        $('.step-' + self.options.steps[i] + ' h2 span.loading').html('<i class="icon16 loading"></i>');
                        $('.step-' + self.options.steps[i]).find('input, select').attr('disabled', 'disabled');
                    }
                }

            });
        },
        formSubmit: function () {
            $("form.checkout-form").on('submit', function () {
                var f = $(this);
                if (f.hasClass('last') || ($("#login-form").length && !$("#login-form input:submit").attr('disabled'))) {
                    return true;
                }

                if ($("form.checkout-form").find('input[name="shipping_id"]').length && !$("form.checkout-form").find('input[name="shipping_id"]:checked').not(':disabled').length) {
                    if (!f.find('em.errormsg').length) {
                        $('<em class="errormsg inline">' + ('[`Please select shipping option`]') + '</em>').insertBefore(f.find('input:submit:last'));
                    }
                    return false;
                } else if ($("form.checkout-form").find('input[name="payment_id"]').length && !$("form.checkout-form").find('input[name="payment_id"]:checked').not(':disabled').length) {
                    if (!f.find('em.errormsg').length) {
                        $('<em class="errormsg inline">' + ('[`Please select payment option`]') + '</em>').insertBefore(f.find('input:submit:last'));
                    }
                    return false;
                } else {
                    f.find('em.errormsg').remove();
                }
                return true;
            });
        },
        initContactinfo: function () {
            this.createUser();
            this.auth();
        },
        initShipping: function () {
            this.checkoutOptions();
            this.addressChange();
            this.shippingRates();
            this.setFirstMethod();
        },
        initPayment: function () {
            this.paymentOptions();
            this.setFirstMethod();
        },
        initConfirmation: function () {

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
                    $("input[type=submit]:last").hide();
                    $("#login-form").show();
                } else {
                    $("#login-form input").attr('disabled', 'disabled');
                    $(".checkout-step").show();
                    $("#login-form").hide();
                    $(this).closest('div.auth').next(".checkout-step-content").show();
                    $("input[type=submit]:last").show();
                }
            });
            $("input[name='user_type']").change();
        },
        checkoutOptions: function () {
            $(".checkout-options input:radio").change(function () {
                if ($(this).is(':checked') && !$(this).data('ignore')) {
                    $(".checkout-options .wa-form").hide();
                    $(this).closest('li').find('.wa-form').show();
                    if ($(this).data('changed')) {
                        $(this).closest('li').find('.wa-form').find('input,select').data('ignore', 1).change().removeData('ignore');
                        $(this).removeData('changed');
                    }
                }
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
        addressChange: function () {
            var self = this;
            $(".wa-address").find('input,select').change(function () {
                if ($(this).data('ignore')) {
                    return true;
                }
                var shipping_id = $("input[name=shipping_id]:checked").val();
                var loaded_flag = false;
                setTimeout(function () {
                    if (!loaded_flag && !$(".shipping-" + shipping_id + " .price .loading").length) {
                        $(".shipping-" + shipping_id + " .price").append(' <i class="icon16 loading"></i>');
                    }
                }, 300);
                var v = $(this).val();
                var name = $(this).attr('name').replace(/customer_\d+/, '');
                $(".checkout-options input:radio").each(function () {
                    if ($(this).val() != shipping_id) {
                        var el = $(this).closest('li').find('[name="customer_' + $(this).val() + name + '"]');
                        if (el.attr('type') != 'hidden') {
                            el.val(v);
                            $(this).data('changed', 1);
                        }
                    }
                });
                $.post(self.options.shipping_url, $("form").serialize(), function (response) {
                    loaded_flag = true;
                    self.responseCallback(shipping_id, response.data);
                }, "json");
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
                var html = '<select class="shipping-rates" name="' + name + '">';
                for (var i = 0; i < data.length; i++) {
                    var r = data[i];
                    html += '<option data-rate="' + r.rate + '" data-comment="' + (r.comment || '') + '" data-est_delivery="' + (r.est_delivery || '') + '" value="' + r.id + '">' + r.name + ' (' + r.rate + ')</option>';
                }
                html += '</select>';
                if (select.length) {
                    var selected = select.val();
                    select.remove();
                } else {
                    var selected = false;
                }
                select = $(html);
                $(".shipping-" + shipping_id + " h3").append(select);
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
                $(".shipping-" + shipping_id + " .price").html(data[0].rate);
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