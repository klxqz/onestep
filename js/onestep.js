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
                $("form.checkout-form").submit(function () {
                    if ($(this).valid()) {
                        $('#checkout-btn').attr('disabled', true);
                    }
                });
            } else {
                $("form.checkout-form").submit(function () {
                    $('#checkout-btn').attr('disabled', true);
                });
            }
            if (this.options.submit) {
                this.errorScroll();
            }
            if (this.options.is_phonemask) {
                this.inputmask();
            }
            this.initSticky();

            var region_field = $('input[name="customer[address.shipping][region]"]');
            if (region_field.length && region_field.val()) {
                var region_name = region_field.val();
                $(document).ajaxComplete(function (event, xhr, settings) {
                    if (settings.url === "/data/regions/") {
                        var field = $('[name="customer[address.shipping][region]"]');
                        if (field.length && field.get(0).tagName == 'SELECT') {
                            field.find('option:contains("' + region_name + '")').attr("selected", true);
                        }
                    }
                });
            }

            var region_shipping_name = [];
            $('ul.checkout-options li input[name=shipping_id]').each(function () {
                var shipping_id = $(this).val();
                var region_field = $('input[name="customer_' + shipping_id + '[address.shipping][region]"]');
                if (region_field.length && region_field.val()) {
                    region_shipping_name[shipping_id] = region_field.val();
                }
            });

            if (region_shipping_name) {
                $(document).ajaxComplete(function (event, xhr, settings) {
                    if (settings.url === "/data/regions/") {

                        $('ul.checkout-options li input[name=shipping_id]').each(function () {
                            var shipping_id = $(this).val();
                            var field = $('[name="customer_' + shipping_id + '[address.shipping][region]"]');
                            if (field.length && field.get(0).tagName == 'SELECT') {
                                field.find('option:contains("' + region_shipping_name[shipping_id] + '")').attr("selected", true);
                            }
                        });


                    }
                });
            }


            this.reloadSteps(['shipping', 'payment', 'confirmation']);
        },
        initSticky: function () {
            console.log(document.body.clientWidth);
            if (!this.options.sticky || document.body.clientWidth < 1024) {
                return;
            }
            window.onload = function () {
                $('.checkout-step.step-confirmation').airStickyBlock({
                    stopBlock: '.checkout-container'
                });
            }

        },
        inputmask: function () {
            $('input[name="customer[phone]"]').inputmask(this.options.phonemask);
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
                errorPlacement: function (error, element) {
                    if ($(element).closest('label').length) {
                        $(element).closest('label').after(error);
                    } else {
                        $(element).after(error);
                    }
                },
                errorElement: "div",
                rules: {
                    terms: {
                        required: true
                    },
                    service_agreement: {
                        required: true
                    }
                },
                messages: {
                    terms: "Это поле обязательное для заполнения",
                    service_agreement: "Это поле обязательное для заполнения"
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
        fullName: function () {
            if ($('[name="customer[name]"]').length) {
                $('[name="customer[firstname]"],[name="customer[lastname]"],[name="customer[middlename]"]').change(function () {
                    var full_name = '';
                    if ($('[name="customer[lastname]"]').length) {
                        full_name += ' ' + $('[name="customer[lastname]"]').val();
                    }
                    if ($('[name="customer[firstname]"]').length) {
                        full_name += ' ' + $('[name="customer[firstname]"]').val();
                    }
                    if ($('[name="customer[middlename]"]').length) {
                        full_name += ' ' + $('[name="customer[middlename]"]').val();
                    }
                    $('[name="customer[name]"]').val(full_name.trim());
                });

            }
        },
        initDaDataFio: function () {
            var self = this;
            if (this.options.dadata_fio) {
                $('[name="customer[name]"]').suggestions({
                    token: this.options.dadata_key,
                    type: "NAME",
                    count: 5,
                    onSelect: function (suggestion) {
                        console.log(suggestion);
                        if ($('[name="customer[firstname]"]').length) {
                            $('[name="customer[firstname]"]').val(suggestion.data.name);
                        }
                        if ($('[name="customer[lastname]"]').length) {
                            $('[name="customer[lastname]"]').val(suggestion.data.surname);
                        }
                        if ($('[name="customer[middlename]"]').length) {
                            $('[name="customer[middlename]"]').val(suggestion.data.patronymic);
                        }
                    }
                });
                $('[name="customer[firstname]"]').suggestions({
                    token: this.options.dadata_key,
                    type: "NAME",
                    params: {
                        parts: ["NAME"]
                    },
                    count: 5,
                    onSelect: function (suggestion) {
                        console.log(suggestion);
                    }
                });
                $('[name="customer[lastname]"]').suggestions({
                    token: this.options.dadata_key,
                    type: "NAME",
                    params: {
                        parts: ["SURNAME"]
                    },
                    count: 5,
                    onSelect: function (suggestion) {
                        console.log(suggestion);
                    }
                });
                $('[name="customer[middlename]"]').suggestions({
                    token: this.options.dadata_key,
                    type: "NAME",
                    params: {
                        parts: ["PATRONYMIC"]
                    },
                    count: 5,
                    onSelect: function (suggestion) {
                        console.log(suggestion);
                    }
                });
            }
        },
        updateAddressField: function (name, value) {

            var match = name.match(/customer\[address.shipping\]\[([^\]]*)\]/);

            if (match && match[1] !== undefined) {
                $('ul.checkout-options li input[name=shipping_id]').each(function () {
                    var shipping_id = $(this).val();
                    var field = $('[name="customer_' + shipping_id + '[address.shipping][' + match[1] + ']"]');
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

            var match = name.match(/customer_[0-9]*\[address.shipping\]\[([^\]]*)\]/);

            if (match && match[1] !== undefined) {
                //console.log(match[1]);
                var field = $('[name="customer[address.shipping][' + match[1] + ']"]');
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
                $('ul.checkout-options li input[name=shipping_id]').each(function () {
                    var shipping_id = $(this).val();
                    var field = $('[name="customer_' + shipping_id + '[address.shipping][' + match[1] + ']"]');
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

        },
        initContactinfo: function () {
            this.createUser();
            var self = this;

            $("#checkout-contact-form .wa-field-address").find('input,select').change(function () {
                if ($(this).data('ignore')) {
                    return true;
                }
                var name = $(this).attr('name');
                var value = $(this).val();
                self.updateAddressField(name, value);
            });
            this.fullName();
            if (this.options.is_dadata) {
                this.initDaDataFio();
                if (this.options.dadata_email) {
                    $('[name="customer[email]"]').suggestions({
                        token: this.options.dadata_key,
                        type: "EMAIL",
                        count: 5,
                        onSelect: function (suggestion) {
                            console.log(suggestion);
                        }
                    });
                }
                if (this.options.dadata_address) {
                    $('[name="customer[address.shipping][street]"]').suggestions({
                        token: this.options.dadata_key,
                        type: "ADDRESS",
                        count: 5,
                        onSelect: function (suggestion) {
                            console.log(suggestion);
                            if ($('[name="customer[address.shipping][zip]"]').length) {
                                $('[name="customer[address.shipping][zip]"]').val(suggestion.data.postal_code);
                            }
                            if ($('[name="customer[address.shipping][city]"]').length) {
                                $('[name="customer[address.shipping][city]"]').val(suggestion.data.city);
                                self.updateAddressField('customer[address.shipping][city]', suggestion.data.city);
                            }
                            if ($('[name="customer[address.shipping][region]"]').length) {
                                if ($('[name="customer[address.shipping][region]"]').get(0).tagName == 'SELECT') {
                                    $('[name="customer[address.shipping][region]"] option').each(function () {
                                        if ($(this).text().indexOf(suggestion.data.region) != -1) {
                                            $(this).attr('selected', true);
                                            self.updateAddressField('customer[address.shipping][region]', $(this).val());
                                        }
                                    });
                                } else {
                                    $('[name="customer[address.shipping][region]"]').val(suggestion.data.region);
                                    self.updateAddressField('customer[address.shipping][region]', suggestion.data.region);
                                }
                            }

                            var value = suggestion.value;
                            if (suggestion.data.city_with_type) {
                                value = value.replace(suggestion.data.city_with_type + ', ', '');
                            }
                            $('[name="customer[address.shipping][street]"]').val(value);
                            self.updateAddressField('customer[address.shipping][street]', value);
                        }
                    });

                    $('[name="customer[address.shipping][city]"]').suggestions({
                        token: this.options.dadata_key,
                        type: "ADDRESS",
                        hint: false,
                        bounds: "city-settlement",
                        count: 5,
                        onSuggestionsFetch: function (suggestions) {
                            return suggestions.filter(function (suggestion) {
                                return suggestion.data.city_district === null;
                            });
                        },
                        onSelect: function (suggestion) {
                            console.log(suggestion);
                            var $region_field = $('[name="customer[address.shipping][region]"]');
                            if ($region_field.length) {
                                if ($region_field.get(0).tagName == 'SELECT') {
                                    $region_field.find('option').each(function () {
                                        if ($(this).text().indexOf(suggestion.data.region) != -1) {
                                            $(this).attr('selected', true);
                                            self.updateAddressField('[name="customer[address.shipping][region]"]', $(this).val());
                                        }
                                    });
                                } else {
                                    $region_field.val(suggestion.data.region);
                                    self.updateAddressField('[name="customer[address.shipping][region]"]', suggestion.data.region);
                                }
                            }

                            var value = suggestion.value;
                            value = value.replace('г ', '');
                            $('[name="customer[address.shipping][city]"]').val(value);
                        }
                    });
                }

            }



            $('#checkout-contact-form input:not([type="checkbox"]),#checkout-contact-form select').change(function () {
                if (
                        $(this).closest('#create-user-div').length ||
                        $(this).attr('name') == 'customer[phone]' ||
                        $(this).attr('name') == 'customer[email]' ||
                        $(this).attr('name') == 'customer[name]' ||
                        $(this).attr('name') == 'customer[firstname]' ||
                        $(this).attr('name') == 'customer[lastname]' ||
                        $(this).attr('name') == 'customer[middlename]'
                        ) {
                    //return false;
                } else {
                    self.reloadSteps(['shipping', 'payment', 'confirmation']);
                }
            });
        },
        initShipping: function () {
            var self = this;
            this.shippingExternalMethods();
            this.shippingOptions();
            this.addressChange();
            this.shippingRates();

            $(".checkout-options .wa-address input,.checkout-options .wa-address select").attr('disabled', true);
            $(".checkout-options [name=shipping_id]:checked").closest('li').find('.wa-address input,.wa-address select').removeAttr('disabled');

            $('ul.checkout-options li input[name=shipping_id]').each(function () {
                var shipping_id = $(this).val();
                var $city_field = $('[name="customer_' + shipping_id + '[address.shipping][city]"]');
                if ($city_field.length) {
                    $city_field.suggestions({
                        token: self.options.dadata_key,
                        type: "ADDRESS",
                        hint: false,
                        bounds: "city-settlement",
                        count: 5,
                        onSuggestionsFetch: function (suggestions) {
                            return suggestions.filter(function (suggestion) {
                                return suggestion.data.city_district === null;
                            });
                        },
                        onSelect: function (suggestion) {
                            console.log(suggestion);
                            var $region_field = $('[name="customer_' + shipping_id + '[address.shipping][region]"]');
                            if ($region_field.length) {
                                if ($region_field.get(0).tagName == 'SELECT') {
                                    $region_field.find('option').each(function () {
                                        if ($(this).text().indexOf(suggestion.data.region) != -1) {
                                            $(this).attr('selected', true);
                                            self.updateAddressField('[name="customer_' + shipping_id + '[address.shipping][region]"]', $(this).val());
                                        }
                                    });

                                } else {
                                    $region_field.val(suggestion.data.region);
                                    self.updateAddressField('[name="customer_' + shipping_id + '[address.shipping][region]"]', suggestion.data.region);
                                }
                            }

                            var value = suggestion.value;
                            value = value.replace('г ', '');
                            $city_field.val(value);
                        }
                    });
                }

            });

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

                    var field = $('#checkout-contact-form .wa-field-address [name="customer[address.shipping][' + match[2] + ']"]');
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
                }

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
            $(".checkout-options.shipping input:radio").change(function () {
                if ($(this).is(':checked') && !$(this).data('ignore')) {
                    $(".checkout-options.shipping .wa-form").hide();
                    $(".checkout-options.shipping .wa-form").find('input,select').attr('disabled', true);
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
                    for (var shipping_id in shipping_external_methods) {
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
            $(".onestep-cart .choice span").click(function () {
                var qty = $(this).siblings('.qty');
                var val = parseInt(qty.val());
                if ($(this).hasClass('plus')) {
                    qty.val(val + 1);
                    qty.change();
                } else if ($(this).hasClass('minus')) {
                    if (val > 1) {
                        qty.val(val - 1);
                        qty.change();
                    }
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
                    $('.please-wait').remove();
                    for (var i in steps) {
                        var step = steps[i];
                        if ($(response).find('.step-' + step).length) {
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
                    $("form.checkout-form").find('#checkout-btn').removeAttr('disabled');
                    self.jqxhr = null;
                    self.reload_steps = [];
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
                var self = this;
                $('.shipping-' + shipping_id + ' .shipping-rates').change(function () {
                    self.reloadSteps(['payment', 'confirmation']);
                });
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