<?php

/**
 * Boxes4ward
 * Contao extension to manage articles and contentelement in content boxes
 *
 * @copyright 4ward.media 2012 <http://www.4wardmedia.de>
 * @author Christoph Wiechert <wio@psitrax.de>
 * @licence LGPL
 * @filesource
 * @package Boxes4ward
 * @see https://github.com/psi-4ward/boxes4ward
 */

/**
 * Extend default palette
 */
$GLOBALS['TL_DCA']['tl_user_group']['palettes']['default'] = str_replace('formp;', 'formp;{boxes4ward_legend},boxes4ward,boxes4ward_newp;', $GLOBALS['TL_DCA']['tl_user_group']['palettes']['default']);


/**
 * Add fields to tl_user_group
 */
$GLOBALS['TL_DCA']['tl_user_group']['fields']['boxes4ward'] = array
(
    'exclude' => true,
    'inputType' => 'checkbox',
    'foreignKey' => 'tl_boxes4ward_category.name',
    'eval' => array('multiple' => true),
    'sql' => 'blob NULL'
);
$GLOBALS['TL_DCA']['tl_user_group']['fields']['boxes4ward_newp'] = array
(
    'exclude' => true,
    'inputType' => 'checkbox',
    'options' => array('create', 'delete'),
    'reference' => &$GLOBALS['TL_LANG']['MSC'],
    'eval' => array('multiple' => true),
    'sql' => 'blob NULL'
);