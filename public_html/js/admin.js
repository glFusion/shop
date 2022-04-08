/**
 * Toggle field for Shop products
 *
 * @param	object  cbox    Checkbox
 * @param	string  id      Sitemap ID, e.g. plugin name
 * @param	string  type    Type of sitemap (XML or HTML)
 */
var SHOP_toggle = function(cbox, id, type, component) {
    oldval = cbox.checked ? 0 : 1;
    var dataS = {
        "action" : "toggle",
        "id": id,
        "type": type,
        "oldval": oldval,
        "component": component,
    };
    data = $.param(dataS);
    $.ajax({
        type: "POST",
        dataType: "json",
        url: site_admin_url + "/plugins/shop/ajax.php",
        data: data,
        success: function(result) {
            try {
                cbox.checked = result.newval == 1 ? true : false;
                if (result.title != null) {
                    cbox.title = result.title;
                }
                Shop.notify(result.statusMessage, 'success');
            }
            catch(err) {
                alert(result.statusMessage);
            }
        },
        error: function(err) {
            console.log(err);
        }
    });
    return false;
};

var SHOPupdateSel = function(sel, id, type, component) {
    newval = sel.value;
    var dataS = {
        "action" : "toggle",
        "id": id,
        "type": type,
        "oldval": newval,
        "component": component,
    };
    data = $.param(dataS);
    $.ajax({
        type: "POST",
        dataType: "json",
        url: site_admin_url + "/plugins/shop/ajax.php",
        data: data,
        success: function(result) {
            try {
                Shop.notify(result.statusMessage, 'success');
            }
            catch(err) {
                alert(result.statusMessage);
            }
        }
    });
}

var SHOP_status = {};

function SHOP_updateOrderStatus(order_id, oldstatus, newstatus, showlog, comment)
{
    var dataS = {
        "action" : "updatestatus",
        "order_id": order_id,
        "newstatus": newstatus,
        "showlog": showlog,
        "comment": comment,
    };
    data = $.param(dataS);
    $.ajax({
        type: "POST",
        dataType: "json",
        url: site_admin_url + "/plugins/shop/ajax.php",
        data: data,
        success: function(jsonObj) {
            try {
                console.log(jsonObj);
                if (jsonObj.showlog == 1) {
                    var tbl = document.getElementById("shopOrderLog");
                    if (tbl) {
                        if (jsonObj.message) {
                            $('#shopOrderLog').append('<tr>' +
                                '<td>' + jsonObj.ts + '</td><td>' + jsonObj.username + '</td><td>' + jsonObj.message +
                                '</td></tr>');
                            var el = document.getElementById("statSelect_" + jsonObj.order_id);
                            el.value = jsonObj.newstatus;
                        }
                        if (jsonObj.comment) {
                            $('#shopOrderLog').append('<tr>' +
                                '<td>' + jsonObj.ts + '</td><td>' + jsonObj.username + '</td><td>' + jsonObj.comment +
                                '</td></tr>');
                        }

                        /*var lastRow = tbl.rows.length;
                        var iteration = lastRow;
                        var row = tbl.insertRow(lastRow);

                        var cell0 = row.insertCell(0);
                        var textNode = document.createTextNode(jsonObj.ts);
                        cell0.appendChild(textNode);

                        var cell1 = row.insertCell(1);
                        var textNode = document.createTextNode(jsonObj.username);
                        cell1.appendChild(textNode);
                        var cell2 = row.insertCell(2);
                        var textNode = document.createTextNode(jsonObj.message);
                        cell2.appendChild(textNode);*/
                    }
                }

                // Hide the button and update the new status in our array
                btn = document.getElementById("shopSetStat_" + jsonObj.order_id);
                if (btn) {
                    btn.style.visibility = "hidden";
                }
                SHOP_setStatus(jsonObj.order_id, jsonObj.newstatus);
                Shop.notify(jsonObj.statusMessage, 'success');
            }
            catch(err) {
                alert("Error Updating");
            }
        }
    });
    return false;
}


/**
 * Show the "update status" submit button if the order status selection has
 * changed.
 */
function SHOP_ordShowStatSubmit(order_id, oldvalue, newvalue)
{
	if (newvalue != oldvalue) {
        $("#shopSetStat_" + order_id).show();
    } else {
        $("#shopSetStat_" + order_id).hide();
    }
}


/**
 * Set the order status value in the dropdown on the admin list.
 */
function SHOP_setStatus(order_id, newstatus)
{
    SHOP_status[order_id] = newstatus;
    sel = document.getElementById("statSelect_" + order_id);
    if (sel != null) {
        sel.selected = newstatus;
    }
}

/**
 * Get the current order status.
 * Just returns the value previusly set in the status array for the admin list.
 */
function SHOP_getStatus(order_id)
{
    return SHOP_status[order_id];
}

function SHOP_voidItem(component, item_id, newval, elem)
{
    var dataS = {
        "item_id": item_id,
        "component": component,
        "action": "void",
        "newval": newval,
    };
    data = $.param(dataS);
    $.ajax({
        type: "POST",
        dataType: "json",
        url: site_admin_url + "/plugins/shop/ajax.php",
        data: data,
        success: function(result) {
            console.log(result);
            try {
                if (result.status) {
                    elem.innerHTML = result.text;
                    elem.className = 'uk-button uk-button-mini ' + result.newclass;
                    elem.setAttribute('onclick',
                        "if (confirm('" + result.confirm_txt + "')) {" +
                        "SHOP_voidItem('" + component + "','" + item_id + "','" + result.onclick_val + "', this);}" +
                        "return false;"
                    );
                    elem.setAttribute('title', result.title);
                }
                if (result.msg != '') {
                    Shop.notify(result.statusMessage, 'success');
                }
            } catch(err) {
            }
        }
    });
    return false;
}

// Enable and disable the action button to update multiple order statuses
// in the order list report.
function SHOP_enaBtn(elem, disa_val, val)
{
    if (val != disa_val) {
        elem.classList.add("uk-button-success");
    } else {
        elem.classList.remove("uk-button-success");
    }
}

