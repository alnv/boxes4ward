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
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\System;
use Psi\Boxes4wardBundle\Model\Article;
use Contao\ModuleModel;
use Contao\Backend;
use Contao\Input;
use Contao\StringUtil;
use Psr\Log\LogLevel;
use Contao\BackendUser;
use Contao\Database;
use Contao\Versions;
use Contao\Image;

/**
 * Table tl_boxes4ward_article
 */
$GLOBALS['TL_DCA']['tl_boxes4ward_article'] = array
(

    // Config
    'config' => array
    (
        'dataContainer' => DC_Table::class,
        'enableVersioning' => true,
        'ptable' => 'tl_boxes4ward_category',
        'ctable' => array('tl_content'),
        'switchToEdit' => true,
        'onload_callback' => array(array('tl_boxes4ward_article', 'checkPermission')),
        'sql' => array
        (
            'keys' => array
            (
                'id' => 'primary',
                'pid' => 'index',
            )
        )
    ),

    // List
    'list' => array
    (
        'sorting' => array
        (
            'mode' => 4,
            'fields' => array('sorting'),
            'panelLayout' => 'filter;search,limit',
            'headerFields' => array('name'),
            'child_record_callback' => array('tl_boxes4ward_article', 'listItem')
        ),
        'label' => array
        (
            'fields' => array('name', 'module_id:tl_module.name'),
            'format' => '%s &nbsp; <span style=;color:#666666">[%s]</span>',
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
                'label' => &$GLOBALS['TL_LANG']['tl_boxes4ward_article']['edit'],
                'href' => 'table=tl_content',
                'icon' => 'edit.svg'
            ),
            'editheader' => array
            (
                'label' => &$GLOBALS['TL_LANG']['tl_boxes4ward_article']['editheader'],
                'href' => 'act=edit',
                'icon' => 'header.svg',
                'button_callback' => array('tl_boxes4ward_article', 'editHeader'),
            ),
            'copy' => array
            (
                'label' => &$GLOBALS['TL_LANG']['tl_boxes4ward_article']['copy'],
                'href' => 'act=copy',
                'icon' => 'copy.svg'
            ),
            'cut' => array
            (
                'label' => &$GLOBALS['TL_LANG']['tl_boxes4ward_article']['cut'],
                'href' => 'act=paste&amp;mode=cut',
                'icon' => 'cut.svg',
                'attributes' => 'onclick="Backend.getScrollOffset()"'
            ),
            'delete' => array
            (
                'label' => &$GLOBALS['TL_LANG']['tl_boxes4ward_article']['delete'],
                'href' => 'act=delete',
                'icon' => 'delete.svg',
                'attributes' => 'onclick="if (!confirm(\'' . ($GLOBALS['TL_LANG']['MSC']['deleteConfirm'] ?? '') . '\')) return false; Backend.getScrollOffset();"'
            ),
            'toggle' => array
            (
                'label' => &$GLOBALS['TL_LANG']['tl_boxes4ward_article']['toggle'],
                'icon' => 'visible.svg',
                'attributes' => 'onclick="Backend.getScrollOffset();return AjaxRequest.toggleVisibility(this,%s)"',
                'button_callback' => array('tl_boxes4ward_article', 'toggleIcon')
            ),
            'show' => array
            (
                'label' => &$GLOBALS['TL_LANG']['tl_boxes4ward_article']['show'],
                'href' => 'act=show',
                'icon' => 'show.svg'
            ),
        )
    ),

    // Palettes
    'palettes' => array
    (
        '__selector__' => array('weekdayFilter', 'monthFilter'),
        'default' => '{name_legend},name,module_id;{config_legend},pages,reversePages,inheritPages;{news_legend},news;{weekday_legend:hide},weekdayFilter;{month_legend:hide},monthFilter;{expert_legend:hide},cssID;{publish_legend},published,start,stop',
    ),

    // Subpalettes
    'subpalettes' => array
    (
        'weekdayFilter' => 'weekdays',
        'monthFilter' => 'monthes',
    ),

    // Fields
    'fields' => array
    (
        'id' => array
        (
            'sql' => "int(10) unsigned NOT NULL auto_increment"
        ),
        'pid' => array
        (
            'foreignKey' => 'tl_boxes4ward_category.name',
            'sql' => "int(10) unsigned NOT NULL default '0'",
            'relation' => array('type' => 'belongsTo', 'load' => 'eager')
        ),
        'tstamp' => array
        (
            'sql' => "int(10) unsigned NOT NULL default '0'"
        ),
        'sorting' => array
        (
            'sql' => "int(10) unsigned NOT NULL default '0'"
        ),
        'name' => array
        (
            'exclude' => true,
            'inputType' => 'text',
            'search' => true,
            'eval' => array('mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'),
            'sql' => "varchar(255) NOT NULL default ''"
        ),
        'pages' => array
        (
            'inputType' => 'pageTree',
            'eval' => array("multiple" => true, 'fieldType' => 'checkbox'),
            'sql' => 'blob NULL'
        ),
        'news' => array
        (
            'inputType' => 'checkboxWizard',
            'options_callback' => array('tl_boxes4ward_article', 'getNewsList'),
            'onsubmit_callback' => array(array('tl_boxes4ward_article', 'getNewsList')),
            'eval' => array('multiple' => true),
            'sql' => 'blob NULL'
        ),
        'module_id' => array
        (
            'exclude' => true,
            'inputType' => 'select',
            'filter' => true,
            'options_callback' => array('tl_boxes4ward_article', 'getModules'),
            'eval' => array('helpwizard' => true, 'tl_class' => 'w50'),
            'sql' => "int(10) unsigned NOT NULL default '0'"
        ),
        'reversePages' => array
        (
            'exclude' => true,
            'filter' => true,
            'inputType' => 'checkbox',
            'eval' => array('tl_class' => 'w50'),
            'sql' => "char(1) NOT NULL default ''"
        ),
        'inheritPages' => array
        (
            'exclude' => true,
            'filter' => true,
            'inputType' => 'checkbox',
            'eval' => array('tl_class' => 'w50'),
            'sql' => "char(1) NOT NULL default ''"
        ),
        'weekdayFilter' => array
        (
            'exclude' => true,
            'filter' => true,
            'inputType' => 'checkbox',
            'eval' => array('submitOnChange' => true),
            'sql' => "char(1) NOT NULL default ''"
        ),
        'weekdays' => array
        (
            'exclude' => true,
            'options' => array('1', '2', '3', '4', '5', '6', '0'),
            'reference' => $GLOBALS['TL_LANG']['DAYS'],
            'inputType' => 'checkbox',
            'eval' => array('multiple' => true),
            'sql' => "blob NULL"
        ),
        'monthFilter' => array
        (
            'exclude' => true,
            'filter' => true,
            'inputType' => 'checkbox',
            'eval' => array('submitOnChange' => true),
            'sql' => "char(1) NOT NULL default ''"
        ),
        'monthes' => array
        (
            'exclude' => true,
            'options' => array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11'),
            'reference' => $GLOBALS['TL_LANG']['MONTHS'],
            'inputType' => 'checkbox',
            'eval' => array('multiple' => true),
            'sql' => "blob NULL"
        ),
        'cssID' => array
        (
            'exclude' => true,
            'inputType' => 'text',
            'eval' => array('multiple' => true, 'size' => 2, 'tl_class' => 'w50'),
            'sql' => "varchar(255) NOT NULL default ''"
        ),
        'published' => array
        (
            'exclude' => true,
            'filter' => true,
            'inputType' => 'checkbox',
            'eval' => array('doNotCopy' => true),
            'sql' => "char(1) NOT NULL default ''"
        ),
        'start' => array
        (
            'exclude' => true,
            'inputType' => 'text',
            'eval' => array('rgxp' => 'datim', 'datepicker' => true, 'tl_class' => 'w50 wizard'),
            'sql' => "varchar(10) NOT NULL default ''"
        ),
        'stop' => array
        (
            'exclude' => true,
            'inputType' => 'text',
            'eval' => array('rgxp' => 'datim', 'datepicker' => true, 'tl_class' => 'w50 wizard'),
            'sql' => "varchar(10) NOT NULL default ''"
        )
    )
);

class tl_boxes4ward_article extends Backend
{

    /**
     * Import the back end user object
     */
    public function __construct()
    {
        parent::__construct();
        $this->import(BackendUser::class, 'User');
        $this->import(Database::class, 'Database');
    }

    /**
     * Generate listItem
     * @param array
     * @return string
     */
    public function listItem($arrRow): string
    {
        $objModule = $this->Database->prepare('SELECT name FROM tl_module WHERE id=?')->execute($arrRow['module_id']);
        if ($objModule->numRows) $arrRow['module_id'] = $objModule->name;
        return '<div class="">' . $arrRow['name'] . '<br><span style="color:#999;font-family:mono;">[' . $arrRow['module_id'] . ']</span></div>' . "\n";
    }

    /**
     * @return array
     */
    public function getModules(): array
    {
        $arrModules = array();
        $objModules = ModuleModel::findBy('type', 'boxes4ward');

        if (!$objModules) return array();

        while ($objModules->next()) {
            $arrModules[$objModules->id] = $objModules->name;
        }

        return $arrModules;
    }

    /**
     * Check permissions to edit table tl_boxes4ward_article
     */
    public function checkPermission()
    {
        if ($this->User->isAdmin) {
            // allow admins
            return;
        }

        // find tl_boxes4archiv.id
        if (!$this->Input->get('act') || in_array($this->Input->get('act'), array('paste', 'create', 'select', 'editAll', 'overrideAll'))) {
            $id = $this->Input->get('id');
        } else {
            $objArticle = $this->Database->prepare('SELECT pid FROM tl_boxes4ward_article WHERE id=?')->execute($this->Input->get('id'));
            $id = $objArticle->pid;
        }

        // check archive rights
        if (is_array($this->User->boxes4ward) && count($this->User->boxes4ward) > 0 && in_array($id, $this->User->boxes4ward)) return;

        System::getContainer()
            ->get('monolog.logger.contao')
            ->log(LogLevel::ERROR, 'Not enough permissions to ' . $this->Input->get('act') . ' boxes4ward archive ID "' . $id . '"', ['contao' => new ContaoContext(__CLASS__ . '::' . __FUNCTION__)]);

        $this->redirect('contao/main.php?act=error');
    }

    /**
     * Return the edit header icon
     * @param $row
     * @param $href
     * @param $label
     * @param $title
     * @param $icon
     * @param $attributes
     * @return string
     */
    public function editHeader($row, $href, $label, $title, $icon, $attributes)
    {
        if (!$this->User->isAdmin && count(preg_grep('/^tl_boxes4ward_article::/', $this->User->alexf)) < 1) {
            return '';
        }

        return '<a href="' . $this->addToUrl($href . '&amp;id=' . $row['id']) . '" title="' . StringUtil::specialchars($title) . '"' . $attributes . '>' . Image::getHtml($icon, $label) . '</a> ';
    }

    /**
     * Return the "toggle visibility" button
     * @param array
     * @param string
     * @param string
     * @param string
     * @param string
     * @param string
     * @return string
     */
    public function toggleIcon($row, $href, $label, $title, $icon, $attributes)
    {
        if (strlen(Input::get('tid'))) {
            $this->toggleVisibility(Input::get('tid'), (Input::get('state') == 1));
            $this->redirect($this->getReferer());
        }

        // Check permissions AFTER checking the tid, so hacking attempts are logged
        if (!$this->User->isAdmin && !$this->User->hasAccess('tl_boxes4ward_article::published', 'alexf')) {
            return '';
        }

        $href .= '&amp;tid=' . $row['id'] . '&amp;state=' . ($row['published'] ? '' : 1);

        if (!$row['published']) {
            $icon = 'invisible.svgsvg';
        }

        return '<a href="' . $this->addToUrl($href) . '" title="' . StringUtil::specialchars($title) . '"' . $attributes . '>' . Image::getHtml($icon, $label) . '</a> ';
    }

    /**
     * Disable/enable a user group
     * @param integer
     * @param boolean
     */
    public function toggleVisibility($intId, $blnVisible)
    {
        // Check permissions to edit
        Input::setGet('id', $intId);
        Input::setGet('act', 'toggle');

        $this->checkPermission();

        // Check permissions to publish
        if (!$this->User->isAdmin && !$this->User->hasAccess('tl_boxes4ward_article::published', 'alexf')) {

            System::getContainer()
                ->get('monolog.logger.contao')
                ->log(LogLevel::ERROR, 'Not enough permissions to publish/unpublish boxes4ward_article ID "' . $intId . '"', ['contao' => new ContaoContext(__CLASS__ . '::' . __FUNCTION__)]);

            $this->redirect('contao/main.php?act=error');
        }

        (new Versions('tl_boxes4ward_article', $intId))->create();

        // Trigger the save_callback
        if (is_array($GLOBALS['TL_DCA']['tl_boxes4ward_article']['fields']['published']['save_callback'])) {
            foreach ($GLOBALS['TL_DCA']['tl_boxes4ward_article']['fields']['published']['save_callback'] as $callback) {
                $this->import($callback[0]);
                $blnVisible = $this->$callback[0]->$callback[1]($blnVisible, $this);
            }
        }

        // Update the database
        $objArticle = Article::findByPk($intId);
        $objArticle->published = ($blnVisible ? '1' : '');
        $objArticle->save();

        (new Versions('tl_boxes4ward_article', $intId))->create();
    }

    public function getNewsList(): array
    {
        $arrNews = array();
        $objNews = $this->Database->prepare("SELECT DISTINCT tl_news.id, headline, date, published, start, stop, tl_news_archive.title AS archive FROM tl_news JOIN tl_news_archive ON tl_news.pid = tl_news_archive.id ORDER BY date DESC")->execute();
        while ($objNews->next()) {
            $arrNews[$objNews->id] = date($GLOBALS['TL_CONFIG']['datimFormat'], $objNews->date) . ' <b>' . $objNews->headline . '</b> [' . $objNews->archive . ']';
        }
        return $arrNews;

    }
}
