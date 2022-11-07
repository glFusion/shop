<?php
/**
 * Admin index page to manage regions for the shop plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020 Lee Garner
 * @package     shop
 * @version     v1.3.0
 * @since       v1.3.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

/** Import Required glFusion libraries */
require_once('../../../lib-common.php');

// If plugin is installed but not enabled, display an error and exit gracefully
if (
    !function_exists('SHOP_access_check') ||
    !SHOP_access_check('shop.admin')
) {
    COM_404();
    exit;
}

require_once('../../auth.inc.php');
USES_lib_admin();

$Request = Shop\Models\Request::getInstance();
$content = '';
$action = 'regions';
$expected = array(
    // Actions to perform
    'saveregion', 'savecountry', 'savestate',
    'ena_region', 'disa_region', 'del_region',
    'ena_country', 'disa_country', 'del_country',
    'ena_state', 'disa_state', 'del_state',
    'rule_add',
    // Views to display
    'editregion', 'editcountry', 'editstate',
    'regions', 'countries', 'states',
);
foreach($expected as $provided) {
    if (isset($Request[$provided])) {
        $action = $provided;
        $actionval = $Request->getString($provided);
        break;
    }
}

switch ($action) {
case 'rule_add':
    // Adds a rule to a state, country or region
    $rule_id = $Request->getInt('rule_id');
    if ($rule_id > 0) {
        switch ($actionval) {
        case 'region':
        case 'country':
        case 'state':
            Shop\Rules\Zone::getInstance($rule_id)
                ->add($actionval, $Request->getArray($actionval . '_id'))
                ->Save();
            break;
        }
    }
    echo COM_refresh(SHOP_ADMIN_URL . '/regions.php?' . http_build_query($_GET));
    break;

case 'saveregion':
    // Save a region record
    $R = Shop\Region::getInstance($Request->getInt('region_id'));
    if ($R->Save($Request)) {
        SHOP_setMsg($LANG_SHOP['msg_updated']);
        echo COM_refresh(SHOP_ADMIN_URL . '/regions.php?regions');
    } else {
        SHOP_setMsg($LANG_SHOP['msg_nochange']);
        echo COM_refresh(SHOP_ADMIN_URL . '/regions.php?editregion=' . $R->getID());
    }
    break;

case 'savecountry':
    // Save a country record
    $C = Shop\Country::getByRecordId($Request->getInt('country_id'));
    if ($C->Save($Request)) {
        SHOP_setMsg($LANG_SHOP['msg_updated']);
        echo COM_refresh(SHOP_ADMIN_URL . '/regions.php?countries');
    } else {
        SHOP_setMsg($C->getErrors());
        $content = $C->Edit($Request);
    }
    break;

case 'savestate':
    // Save a state record
    $S = Shop\State::getByRecordId($Request->getInt('state_id'));
    if ($S->Save($Request)) {
        SHOP_setMsg($LANG_SHOP['msg_updated']);
        echo COM_refresh(SHOP_ADMIN_URL . '/regions.php?states');
    } else {
        SHOP_setMsg($LANG_SHOP['msg_nochange']);
        echo COM_refresh(SHOP_ADMIN_URL . '/regions.php?editstate=' . $S->getID());
    }
    break;

case 'ena_region':
    $regions = $Requst->getArray('region_id');
    if (!empty($regions)) {
        Shop\Region::BulkToggle(0, 'region_enabled', $regions);
    }
    echo COM_refresh(SHOP_ADMIN_URL . '/regions.php?regions');
    break;

case 'disa_region':
    $regions = $Request->getArray('region_id');
    if (!empty($regions)) {
        Shop\Region::BulkToggle(1, 'region_enabled', $regions);
    }
    echo COM_refresh(SHOP_ADMIN_URL . '/regions.php?regions');
    break;


case 'ena_country':
    $countries = $Request->getArray('country_id');
    if (!empty($countries)) {
        Shop\Country::BulkToggle(0, 'country_enabled', $countries);
    }
    echo COM_refresh(SHOP_ADMIN_URL . '/regions.php?countries');
    break;

case 'disa_country':
    $countries = $Request->getArray('country_id');
    if (!empty($countries)) {
        Shop\Country::BulkToggle(1, 'country_enabled', $countries);
    }
    echo COM_refresh(SHOP_ADMIN_URL . '/regions.php?countries');
    break;

case 'ena_state':
    $states = $Request->getArray('state_id');
    if (!empty($states)) {
        Shop\State::BulkToggle(0, 'state_enabled', $states);
    }
    echo COM_refresh(SHOP_ADMIN_URL . '/regions.php?states');
    break;

case 'disa_state':
    $states = $Request('state_id');
    if (!empty($states)) {
        Shop\State::BulkToggle(1, 'state_enabled', $states);
    }
    echo COM_refresh(SHOP_ADMIN_URL . '/regions.php?states');
    break;

case 'editregion':
    $region_id = (int)$actionval;
    $content .= Shop\Menu::adminRules('regions');
    $content .= Shop\Region::getInstance($region_id)->Edit();
    break;

case 'editcountry':
    $country_id = (int)$actionval;
    $content .= Shop\Menu::adminRules('countries');
    $content .= Shop\Country::getByRecordId($country_id)->Edit();
    break;

case 'editstate':
    $state_id = (int)$actionval;
    $content .= Shop\Menu::adminRules('states');
    $content .= Shop\State::getByRecordId($state_id)->Edit();
    break;

case 'countries':
    $region_id = $Request->getInt('region_id');
    $content .= Shop\Menu::adminRules($action);
    $content .= Shop\Country::adminList($region_id);
    break;

case 'states':
    $country_id = $Request->getInt('country_id');
    $content .= Shop\Menu::adminRules($action);
    $content .= Shop\State::adminList($country_id);
    break;

case 'regions':
default:
    $content .= Shop\Menu::adminRules($action);
    $content .= Shop\Region::adminList();
    break;
}

$display = COM_siteHeader();
$display .= \Shop\Menu::Admin($action);
if (!empty($msg)) {
    $messages = implode('<br />', $msg);
    $display .= COM_showMessageText($messages);
}
$display .= $content;
$display .= COM_siteFooter();
echo $display;
exit;

?>
