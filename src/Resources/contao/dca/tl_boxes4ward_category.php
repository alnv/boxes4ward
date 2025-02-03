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

use Contao\DC_Table;
use Contao\Backend;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\Image;
use Contao\StringUtil;
use Contao\System;
use Contao\BackendUser;
use Psr\Log\LogLevel;

/**
 * Table tl_boxes4ward_category
 */
$GLOBALS['TL_DCA']['tl_boxes4ward_category'] = array
(

    // Config
    'config' => array
    (
        'dataContainer' => DC_Table::class,
        'enableVersioning' => true,
        'ctable' => array('tl_boxes4ward_article'),
        'switchToEdit' => true,
        'onload_callback' => array(array('tl_boxes4ward_category', 'checkPermission')),
        'sql' => array
        (
            'keys' => array
            (
                'id' => 'primary',
            )
        )
    ),

    // List
    'list' => array
    (
        'sorting' => array
        (
            'mode' => 1,
            'fields' => array('name'),
            'flag' => 1,
            'panelLayout' => 'filter;search,limit',
        ),
        'label' => array
        (
            'fields' => array('name'),
            'format' => '%s',
        ),
        'global_operations' => array
        (
            'all' => array
            (
                'label' => &$GLOBALS['TL_LANG']['MSC']['all'],
                'href' => 'act=select',
                'class' => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset();" accesskey="e"'
            ),
        ),
        'operations' => array
        (
            'edit' => array
            (
                'label' => &$GLOBALS['TL_LANG']['tl_boxes4ward_category']['edit'],
                'href' => 'table=tl_boxes4ward_article',
                'icon' => 'edit.svg'
            ),
            'editheader' => array
            (
                'label' => &$GLOBALS['TL_LANG']['tl_boxes4ward_category']['editheader'],
                'href' => 'act=edit',
                'icon' => 'header.svg'
            ),
            'copy' => array
            (
                'label' => &$GLOBALS['TL_LANG']['tl_boxes4ward_category']['copy'],
                'href' => 'act=copy',
                'icon' => 'copy.svg',
                'button_callback' => array('tl_boxes4ward_category', 'copyArchive')
            ),
            'delete' => array
            (
                'label' => &$GLOBALS['TL_LANG']['tl_boxes4ward_category']['delete'],
                'href' => 'act=delete',
                'icon' => 'delete.svg',
                'attributes' => 'onclick="if (!confirm(\'' . ($GLOBALS['TL_LANG']['MSC']['deleteConfirm'] ?? '') . '\')) return false; Backend.getScrollOffset();"',
                'button_callback' => array('tl_boxes4ward_category', 'deleteArchive')
            ),
            'show' => array
            (
                'label' => &$GLOBALS['TL_LANG']['tl_boxes4ward_category']['show'],
                'href' => 'act=show',
                'icon' => 'show.svg'
            ),
        )
    ),

    // Palettes
    'palettes' => array
    (
        'default' => '{name_legend},name;{publish_legend},published,start,stop',
    ),

    // Fields
    'fields' => array
    (
        'id' => array
        (
            'sql' => "int(10) unsigned NOT NULL auto_increment"
        ),
        'tstamp' => array
        (
            'sql' => "int(10) unsigned NOT NULL default '0'"
        ),
        'name' => array
        (
            'exclude' => true,
            'inputType' => 'text',
            'eval' => array('mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'),
            'sql' => "varchar(255) NOT NULL default ''"
        ),
    )
);


class tl_boxes4ward_category extends Backend
{

    /**
     * Import the back end user object
     */
    public function __construct()
    {
        parent::__construct();
        $this->import(BackendUser::class, 'User');
    }

    /**
     * Check permissions to edit table tl_boxes4ward
     */
    public function checkPermission()
    {
        if ($this->User->isAdmin) {
            return;
        }

        // Set root IDs
        if (!is_array($this->User->boxes4ward) || count($this->User->boxes4ward) < 1) {
            $root = array(0);
        } else {
            $root = $this->User->boxes4ward;
        }

        $GLOBALS['TL_DCA']['tl_boxes4ward_category']['list']['sorting']['root'] = $root;

        // Check permissions to add archives
        // if a no add-permissions, implict no edit-permission
        if (!$this->User->hasAccess('create', 'boxes4ward_newp')) {
            $GLOBALS['TL_DCA']['tl_boxes4ward_category']['config']['closed'] = true;
            unset($GLOBALS['TL_DCA']['tl_boxes4ward_category']['list']['operations']['editheader']);
        }

        $objSession = System::getContainer()->get('request_stack')->getSession();

        // Check current action
        switch ($this->Input->get('act')) {
            case 'create':
            case 'select':
                // Allow
                break;

            case 'edit':
                // Dynamically add the record to the user profile
                if (!in_array($this->Input->get('id'), $root)) {
                    $arrNew = $this->Session->get('new_records');

                    if (is_array($arrNew['tl_boxes4ward_category']) && in_array($this->Input->get('id'), $arrNew['tl_boxes4ward_category'])) {
                        // Add permissions on user level
                        // @todo if rights are extended, add to group instead!
                        // but BackendUser inherits no rights for boxes4ward-row
                        if ($this->User->inherit == 'custom' || !$this->User->groups[0] || $this->User->inherit == 'extend') {
                            $objUser = $this->Database->prepare("SELECT boxes4ward, boxes4ward_newp FROM tl_user WHERE id=?")
                                ->limit(1)
                                ->execute($this->User->id);

                            $arrNewp = StringUtil::deserialize($objUser->boxes4ward_newp);

                            if (is_array($arrNewp) && in_array('create', $arrNewp)) {
                                $arrNews = StringUtil::deserialize($objUser->boxes4ward);
                                $arrNews[] = $this->Input->get('id');

                                $this->Database->prepare("UPDATE tl_user SET boxes4ward=? WHERE id=?")
                                    ->execute(serialize($arrNews), $this->User->id);
                            }
                        } // Add permissions on group level
                        elseif ($this->User->groups[0] > 0) {
                            $objGroup = $this->Database->prepare("SELECT boxes4ward, boxes4ward_newp FROM tl_user_group WHERE id=?")
                                ->limit(1)
                                ->execute($this->User->groups[0]);

                            $arrNewp = StringUtil::deserialize($objGroup->boxes4ward_newp);

                            if (is_array($arrNewp) && in_array('create', $arrNewp)) {
                                $arrNews = StringUtil::deserialize($objGroup->boxes4ward);
                                $arrNews[] = $this->Input->get('id');

                                $this->Database->prepare("UPDATE tl_user_group SET boxes4ward=? WHERE id=?")
                                    ->execute(serialize($arrNews), $this->User->groups[0]);
                            }
                        }

                        // Add new element to the user object
                        $root[] = $this->Input->get('id');
                        $this->User->boxes4ward = $root;
                    }
                }
            // No break;

            case 'copy':
            case 'delete':
            case 'show':
                if (!in_array($this->Input->get('id'), $root) || ($this->Input->get('act') == 'delete' && !$this->User->hasAccess('delete', 'boxes4ward_newp'))) {

                    System::getContainer()
                        ->get('monolog.logger.contao')
                        ->log(LogLevel::ERROR, 'Not enough permissions to ' . $this->Input->get('act') . ' boxes4ward category ID "' . $this->Input->get('id') . '"', ['contao' => new ContaoContext(__CLASS__ . '::' . __FUNCTION__)]);

                    $this->redirect('contao/main.php?act=error');
                }
                break;

            case 'editAll':
            case 'overrideAll':
                $session = $objSession->getData();
                if (!$this->User->hasAccess('create', 'boxes4ward_newp')) {
                    $session['CURRENT']['IDS'] = array();
                } else {
                    $session['CURRENT']['IDS'] = array_intersect($session['CURRENT']['IDS'], $root);
                }
                $objSession->setData($session);
                break;

            case 'deleteAll':
                $session = $objSession->getData();
                if ($this->Input->get('act') == 'deleteAll' && !$this->User->hasAccess('delete', 'boxes4ward_newp')) {
                    $session['CURRENT']['IDS'] = array();
                } else {
                    $session['CURRENT']['IDS'] = array_intersect($session['CURRENT']['IDS'], $root);
                }
                $objSession->setData($session);
                break;

            default:
                if (strlen($this->Input->get('act'))) {

                    System::getContainer()
                        ->get('monolog.logger.contao')
                        ->log(LogLevel::ERROR, 'Not enough permissions to ' . $this->Input->get('act') . ' boxes4ward categories', 'tl_boxes4ward_category checkPermission', ['contao' => new ContaoContext(__CLASS__ . '::' . __FUNCTION__)]);

                    $this->redirect('contao/main.php?act=error');
                }
                break;
        }
    }

    /**
     * Return the copy archive button
     * @param array
     * @param string
     * @param string
     * @param string
     * @param string
     * @param string
     * @return string
     */
    public function copyArchive($row, $href, $label, $title, $icon, $attributes)
    {
        return ($this->User->isAdmin || $this->User->hasAccess('create', 'boxes4ward_newp')) ? '<a href="' . $this->addToUrl($href . '&amp;id=' . $row['id']) . '" title="' . StringUtil::specialchars($title) . '"' . $attributes . '>' . Image::getHtml($icon, $label) . '</a> ' : ' ';
    }

    /**
     * Return the delete archive button
     * @param array
     * @param string
     * @param string
     * @param string
     * @param string
     * @param string
     * @return string
     */
    public function deleteArchive($row, $href, $label, $title, $icon, $attributes)
    {
        return ($this->User->isAdmin || $this->User->hasAccess('delete', 'boxes4ward_newp')) ? '<a href="' . $this->addToUrl($href . '&amp;id=' . $row['id']) . '" title="' . StringUtil::specialchars($title) . '"' . $attributes . '>' . Image::getHtml($icon, $label) . '</a> ' : ' ';
    }
}