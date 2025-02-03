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

use Psi\Boxes4wardBundle\Module\Boxes4ward;
use Psi\Boxes4wardBundle\Model\Article;
use Psi\Boxes4wardBundle\Model\Category;

// BE-Module
$GLOBALS['BE_MOD']['content']['boxes4ward'] = [
    'tables' => ['tl_boxes4ward_category', 'tl_boxes4ward_article', 'tl_content']
];

// FE-Modules
$GLOBALS['FE_MOD']['miscellaneous']['boxes4ward'] = Boxes4ward::class;

// add news archive permissions
$GLOBALS['TL_PERMISSIONS'][] = 'boxes4ward';
$GLOBALS['TL_PERMISSIONS'][] = 'boxes4ward_newp';

// Models
$GLOBALS['TL_MODELS']['tl_boxes4ward_category'] = Category::class;
$GLOBALS['TL_MODELS']['tl_boxes4ward_article'] = Article::class;