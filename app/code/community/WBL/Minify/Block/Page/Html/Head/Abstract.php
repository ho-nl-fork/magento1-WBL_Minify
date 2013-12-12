<?php
/**
 * WBL_Minfy
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the H&O Commercial License
 * that is bundled with this package in the file LICENSE_HO.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.h-o.nl/license
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to info@h-o.com so we can send you a copy immediately.
 *
 * @category    WBL
 * @package     WBL_Minfy
 * @copyright   Copyright © 2013 H&O (http://www.h-o.nl/)
 * @license     H&O Commercial License (http://www.h-o.nl/license)
 * @author      Paul Hachmang – H&O <info@h-o.nl>
 *
 * 
 */

if (Mage::helper('core')->isModuleEnabled('Aoe_JsCssTstamp')) {
    class WBL_Minify_Block_Page_Html_Head_Abstract extends Aoe_JsCssTstamp_Block_Head {}
} else {
    class WBL_Minify_Block_Page_Html_Head_Abstract extends Mage_Page_Block_Html_Head {}
}
