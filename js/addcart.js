/**
 * Shop plugin javascript.
 */

/*
 * Add an item to the shopping cart.
 */
var shopAddToCart = function(frm_id, nonce)
{
    if (typeof(nonce) == "undefined") {
        // Get the data from form fields
        data = $("#"+frm_id).serialize();
    } else {
        // If a nonce is provided, then frm_id is really an item number
        // and this is directly adding one of the item to the cart.
        data = "item_number=" + frm_id + "&nonce=" + nonce;
    }

    var spinner = UIkit.modal.blockUI('<div class="uk-text-large uk-text-center"><i class="uk-icon-spinner uk-icon-large uk-icon-spin"></i></div>', {center:true});
    spinner.show();
    $.ajax({
        type: "POST",
        dataType: "json",
        url: glfusionSiteUrl + "/shop/ajax.php?action=addcartitem",
        data: data,
        success: function(result) {
            try {
                if (result.content != '') {
                    // Update the shopping cart block if it is displayed
                    $("#shopCartBlockContents").html(result.content);
                    if (result.unique) {
                        $("#"+frm_id+"_add_cart_btn").prop('disabled', true);
                    }
                    $.UIkit.notify(
                        "<i class='uk-icon-check'></i>&nbsp;" + result.statusMessage,
                        {timeout: 1000,pos:'top-center'}
                    );
                    // If a return URL is provided, redirect to that page
                    if (result.ret_url != '') {
                        window.location.href = result.ret_url;
                    }
                }
            } catch(err) {
            }
            spinner.hide();
            blk_setvis_shop_cart(result.content == "" ? "none" : "block");
        },
        error: function() {
            spinner.hide();
        }
    });
    return false;
};

function SHOP_delFromCart(oi_id)
{
    var dataS = {
        "oi_id": oi_id,
    };
    var data = $.param(dataS);
    $.ajax({
        type: "GET",
        dataType: "json",
        url: glfusionSiteUrl + "/shop/ajax.php?action=delcartitem",
        data: data,
        success: function(result) {
            try {
                if (result.content != '') {
                    // Update the shopping cart block if it is displayed
                    $("#shopCartBlockContents").html(result.content);
                }
            } catch(err) {
            }
            blk_setvis_shop_cart(result.content == "" ? "none" : "block");
        },
        error: function() {
        }
    });
    return false;
}


/**
 * Set the visibility of the cart block so it only appears if there are items
 */
function blk_setvis_shop_cart(newvis)
{
    blk = document.getElementById("shop_cart");
    if (typeof(blk) != 'undefined' && blk != null) {
        blk.style.display = newvis;
    }
    btn = document.getElementById("link_cart");
    if (typeof(btn) != 'undefined' && btn != null) {
        btn.style.display = newvis == "block" ? "" : "none";
    }
}

/**
 * Finalize the cart.
 */
function finalizeCart(cart_id, uid, redirects=false)
{
    // First check that there is a payer email filled out.
/*    if (document.frm_checkout.payer_email.value == "") {
        return false;
    }
*/
    var spinner = UIkit.modal.blockUI('<div class="uk-text-large uk-text-center"><i class="uk-icon-spinner uk-icon-large uk-icon-spin"></i></div>', {center:true});
    spinner.show();
    var dataS = {
        "cart_id": cart_id,
        "uid": uid,
    };
    var data = $.param(dataS);
    var stat = true;
    $.ajax({
        type: "POST",
        dataType: "json",
        async: false,
        url: glfusionSiteUrl + "/shop/ajax.php?action=finalizecart",
        data: data,
        success: function(result) {
            try {
                if (result.status == true) {
                    stat = true;
                } else {
                    stat = false;
                }
            } catch(err) {
                stat = false;
            }
            if (!redirects) {
                spinner.hide();
            }
        },
        error: function(httpRequest, message, errorThrown) {
            console.log(cart_id);
            console.log(httpRequest);
            console.log(message);
            console.log(errorThrown);
            throw errorThrown + ': ' + message;
            spinner.hide();
            return false;
        },
    });
    return stat;
}

/**
 * Apply a gift card to the cart. Get the amount from the form.
 */
var shopApplyGC = function(frm_id)
{
    data = $("#"+frm_id).serialize();
    $.ajax({
        type: "POST",
        dataType: "json",
        url: glfusionSiteUrl + "/shop/ajax.php?action=redeem_gc",
        data: data,
        success: function(result) {
            try {
                if (result.status == 0) {
                    if (result.html != '') {
                        // Update the gateway selection
                        divid = document.getElementById("gwrad__coupon");
                        if (divid != undefined) {
                            divid.innerHTML = result.html;
                        }
                    }
                }
                if (result.msg != '') {
                    $.UIkit.notify("<i class='uk-icon-check'></i>&nbsp;" + result.statusMessage, {timeout: 2000,pos:'top-center'});
                }
                document.getElementById('enterGC').value = '';
            } catch(err) {
            }
        }
    });
    return false;
};

// Change the country selection on an address form
// Used for customer and supplier addresses
function chgCountrySel(newcountry)
{
    var dataS = {
        "action": "getStateOpts",
        "country_iso": newcountry,
    };
    data = $.param(dataS);
    $.ajax({
        type: "GET",
        dataType: "json",
        url: glfusionSiteUrl + "/shop/ajax.php",
        data: data,
        success: function(result) {
            try {
                if (result.status && result.opts.length > 0) {
                    $("#stateSelect").html(result.opts);
                    $("#stateSelectDiv").show();
                } else {
                    $("#stateSelectDiv").hide();
                }
            }
            catch(err) {
            }
        }
    });
    return;
}

function SHOPvalidateAddress(form)
{
    if (typeof(form) == 'undefined') {
        return;
    }
    data = $("#" + form.id).serialize();
    $.ajax({
        type: "POST",
        dataType: "json",
        url: glfusionSiteUrl + "/shop/ajax.php?action=validateAddress",
        data: data,
        success: function(result) {
            try {
                if (result.status != true) {
                    modal = UIkit.modal.blockUI(result.form);
                } else {
                    var input = document.createElement("input");
                    input.type = "hidden";
                    input.name = "{action}";
                    input.value = "x";;
                    form.appendChild(input);
                    form.submit();
                }
            }
            catch(err) {
            }
        }
    });
    return;
}

