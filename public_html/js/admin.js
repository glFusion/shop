/**
*   Toggle field for Shop products
*
*   @param  object  cbox    Checkbox
*   @param  string  id      Sitemap ID, e.g. plugin name
*   @param  string  type    Type of sitemap (XML or HTML)
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
            cbox.checked = result.newval == 1 ? true : false;
            try {
                $.UIkit.notify("<i class='uk-icon-check'></i>&nbsp;" + result.statusMessage, {timeout: 1000,pos:'top-center'});
            }
            catch(err) {
                alert(result.statusMessage);
            }
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
                $.UIkit.notify("<i class='uk-icon-check'></i>&nbsp;" + result.statusMessage, {timeout: 1000,pos:'top-center'});
            }
            catch(err) {
                alert(result.statusMessage);
            }
        }
    });
}

var SHOP_status = {};

function SHOP_updateOrderStatus(order_id, oldstatus, newstatus, showlog)
{
    var dataS = {
        "action" : "updatestatus",
        "order_id": order_id,
        "newstatus": newstatus,
        "showlog": showlog,
    };
    data = $.param(dataS);
    $.ajax({
        type: "POST",
        dataType: "json",
        url: site_admin_url + "/plugins/shop/ajax.php",
        data: data,
        success: function(jsonObj) {
            try {
                if (jsonObj.showlog == 1) {
                    var tbl = document.getElementById("shopOrderLog");
                    if (tbl) {
                        var lastRow = tbl.rows.length;
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
                        cell2.appendChild(textNode);
                    }
                }
                var el = document.getElementById("statSelect_" + jsonObj.order_id);
                el.value = jsonObj.newstatus;

                // Hide the button and update the new status in our array
                document.getElementById("shopSetStat_" + jsonObj.order_id).style.visibility = "hidden";
                SHOP_setStatus(jsonObj.order_id, jsonObj.newstatus);
                $.UIkit.notify("<i class='uk-icon-check'></i>&nbsp;" + jsonObj.message, {timeout: 1000,pos:'top-center'});
            }
            catch(err) {
                alert(result.message);
            }
        }
    });
    return false;
}


/*  Show the "update status" submit button if the order status selection has
    changed.
*/
function SHOP_ordShowStatSubmit(order_id, oldvalue, newvalue)
{
    var el = document.getElementById("shopSetStat_" + order_id);
    if (el) {
        if (newvalue != oldvalue) {
            el.style.visibility = '';
        } else {
            el.style.visibility = 'hidden';
        }
    }
}

function SHOP_setStatus(order_id, newstatus)
{
    SHOP_status[order_id] = newstatus;
    sel = document.getElementById("statSelect_" + order_id);
    if (sel != null) {
        sel.selected = newstatus;
    }
}

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
            try {
                if (result.status) {
                    elem.innerHTML = result.text;
                    elem.className = 'tooltip uk-button uk-button-mini ' + result.newclass;
                    elem.setAttribute('onclick',
                        "if (confirm('" + result.confirm_txt + "')) {" +
                        "voidItem('" + component + "','" + item_id + "','" + result.onclick_val + "', this);}" +
                        "return false;"
                    );
                }
                if (result.msg != '') {
                    $.UIkit.notify("<i class='uk-icon-check'></i>&nbsp;" + result.statusMessage, {timeout: 2000,pos:'top-center'});
                }
            } catch(err) {
            }
        }
    });
    return false;
}

