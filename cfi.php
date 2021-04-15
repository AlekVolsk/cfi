<?php defined('_JEXEC') or die;
/**
 * @package     Joomla.Plugin
 * @subpackage  System.cfi
 * @copyright   Copyright (C) Aleksey A. Morozov. All rights reserved.
 * @license     GNU General Public License version 3 or later; see http://www.gnu.org/licenses/gpl-3.0.txt
 */

use Joomla\Registry\Registry;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\URI\URI;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Filesystem\Path;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Filter\OutputFilter;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

\JLoader::register('FieldsHelper', JPATH_ADMINISTRATOR . '/components/com_fields/helpers/fields.php');

class plgSystemCfi extends CMSPlugin
{
    // UTF BOM signature
    private $BOM = [
        "\xEF\xBB\xBF", // UTF-8
        "п»ї" // UTF-8 OO
    ];

    private $_app;
    private $_doc;
    private $_user;
    private $_file = null;
    private $_cp;
    private $_fieldPlugins;
    protected $autoloadLanguage = true;

    public function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);
        $this->_initConstruct();
    }

    private function _initConstruct($ajax = false)
    {
        $this->_app = Factory::getApplication('administrator');
        $this->_doc = Factory::getDocument();

        if (!$this->_app->isClient('administrator')) {
            return;
        }

        if ($ajax) {
            $option = $this->_app->input->get('option');
            $view   = $this->_app->input->get('view');
            if (!($option == 'com_content' && (in_array($view, ['articles', 'featured', ''])))) {
                return;
            }
        } else {
            $this->_doc->addScript(URI::root(true) . '/plugins/system/cfi/assets/cfi.js');
            $this->_doc->addStylesheet(URI::root(true) . '/plugins/system/cfi/assets/cfi.css');
        }

        $user = Factory::getUser();
        $this->_user = $user->id . ':' . $user->username;

        $this->_cp = $this->params->get('cp', 'CP1251');

        $this->_fieldPlugins = [
            'imagelist' => 0,
            'integer' => 0,
            'list' => 0,
            'sql' => 0,
            'usergrouplist' => 0
        ];
        $plugins = PluginHelper::getPlugin('fields');
        foreach ($plugins as $key => $plugin) {
            $plugins[$plugin->name] = $plugin;
            unset($plugins[$key]);
            $plugins[$plugin->name]->params = new Registry($plugins[$plugin->name]->params);
        }
        foreach (array_keys($this->_fieldPlugins) as $pluginName) {
            $multiple = $plugins[$pluginName]->params->get('multiple', -1);
            if ($multiple >= 0) {
                $this->_fieldPlugins[$pluginName] = (int)$multiple;
            }
        }

        BaseDatabaseModel::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_fields/models', 'FieldsModel');
        BaseDatabaseModel::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_content/models/', 'ContentModel');
    }

    public function onBeforeRender()
    {
        if ($this->_doc->getType() != 'html' || !$this->_app->isClient('administrator')) {
            return;
        }

        $option = $this->_app->input->get('option');
        $view   = $this->_app->input->get('view');
        if (!($option == 'com_content' && (in_array($view, ['articles', 'featured', ''])))) {
            return;
        }

        $toolbar = new FileLayout('toolbar', Path::clean(JPATH_PLUGINS . '/system/cfi/layouts'));
        ToolBar::getInstance('toolbar')->appendButton('Custom', $toolbar->render([]), 'cfi');

        return true;
    }

    public function onAfterRender()
    {
        if ($this->_doc->getType() != 'html' || !$this->_app->isClient('administrator')) {
            return;
        }

        $option = $this->_app->input->get('option');
        $view   = $this->_app->input->get('view');
        if (!($option == 'com_content' && (in_array($view, ['articles', 'featured', ''])))) {
            return;
        }

        $html = $this->_app->getBody();

        if (strpos($html, '</head>') !== false) {
            list($head, $content) = explode('</head>', $html, 2);
        } else {
            $content = $html;
        }

        if (empty($content)) {
            return false;
        }

        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('id, title')
            ->from('#__categories')
            ->where('extension = "com_content"')
            ->order('title');
        $db->setQuery($query);
        try {
            $categories = $db->loadObjectList();
        } catch (Exception $e) {
            $categories = [];
        }

        $well = new FileLayout('well', Path::clean(JPATH_PLUGINS . '/system/cfi/layouts'));
        $matches = [];
        preg_match('#id="j-main-container" (\w+)(.*?)>#i', $content, $matches);
        if ($matches && $matches[0]) {
            $wellParams = [
                'cp' => $this->_cp,
                'categories' => $categories,
                'showdesc' => $this->params->get('showdesc', 1)
            ];
            $content = str_replace($matches[0], $matches[0] . $well->render($wellParams), $content);
            $html = isset($head) ? ($head . '</head>' . $content) : $content;
            $this->_app->setBody($html);
            return true;
        }

        return;
    }

    public function onAjaxCfi()
    {
        Log::addLogger(['text_file' => 'cfi.php', 'text_entry_format' => "{DATETIME}\t{PRIORITY}\t{MESSAGE}"], Log::ALL);

        $this->_initConstruct(true);

        $state = $this->_app->input->get('cfistate', '');

        if (!Session::checkToken($state == 'download' ? 'get' : 'post')) {
            $data = [
                'result' => Text::_('JINVALID_TOKEN'),
                'user' => $this->_user,
                'file' => $this->_app->input->files->getArray(),
                'get' => $this->_app->input->get->getArray(),
                'post' => $this->_app->input->post->getArray()
            ];
            Log::add(json_encode($data), Log::ERROR);
            $this->_printJson($data['result']);
        }

        if ($state == 'import') {
            $this->_checkFile($this->_app->input->files->get('cfifile'));
            $this->_importData();
        }

        if ($state == 'export') {
            $this->_exportData();
        }

        if ($state == 'download') {
            $this->_file = $this->_app->input->get('f', '');
            if ($this->_file) {
                $this->_file = Path::clean(Factory::getConfig()->get('tmp_path') . '/' . urldecode($this->_file));
                $this->_fileDownload($this->_file);
                @unlink($this->_file);
            }
        }
    }

    private function _printJson($message = '', $result = false, $custom = [])
    {
        $custom['result'] = $result;
        $custom['message'] = $message;
        echo json_encode($custom);
        exit;
    }

    private function _checkFile($file)
    {
        $data = [
            'result' => '',
            'user' => $this->_user,
            'file' => $file
        ];

        if (is_array($file) && count($file)) {

            if ($file['error'] != 0) {
                $data['result'] = Text::_('PLG_CFIfile_ERROR');
                Log::add(json_encode($data), Log::ERROR);
                $this->_printJson($data['result']);
            }

            if (!$file['size']) {
                $data['result'] = Text::_('PLG_CFIfile_SIZE');
                Log::add(json_encode($data), Log::ERROR);
                $this->_printJson($data['result']);
            }

            if (pathinfo($file['name'], PATHINFO_EXTENSION) !== 'csv') {
                $data['result'] = Text::_('PLG_CFIfile_TYPE');
                Log::add(json_encode($data), Log::ERROR);
                $this->_printJson($data['result']);
            }

            $this->_file = Path::clean(Factory::getConfig()->get('tmp_path') . '/cfi_' . date('Y-m-d-H-i-s') . '.csv');
            if (!@move_uploaded_file($file['tmp_name'], $this->_file)) {
                $data['result'] = Text::_('PLG_CFIfile_MOVE');
                Log::add(json_encode($data), Log::ERROR);
                $this->_printJson($data['result']);
            }

            return true;
        }

        $data['result'] = Text::_('PLG_CFIfile_NOTHING');
        Log::add(json_encode($data), Log::ERROR);
        $this->_printJson($data['result']);
    }

    private function _importData()
    {
        // log template
        $data = [
            'result' => '',
            'user' => $this->_user,
            'file' => $this->_file
        ];

        // get categories
        $categories = $this->_getCategories();
        if (!$categories) {
            $data['result'] = Text::_('PLG_CFI_IMPORT_GET_CATEGORIES');
            Log::add(json_encode($data), Log::ERROR);
            $this->_printJson($data['result']);
        }

        // get file content
        $content = trim(file_get_contents($this->_file));

        // convert to UTF-8
        $isConvert = (int) $this->_app->input->get('cficonvert', 0);

        if ($isConvert > 0) {
            $content = mb_convert_encoding($content, 'UTF-8', $this->_cp);
        }

        // unset utf-8 bom
        $content = str_replace($this->BOM, '', $content);

        // line separator definition
        $rowDelimiter = "\r\n";
        if (false === strpos($content, "\r\n")) {
            $rowDelimiter = "\n";
        }

        // get lines array
        $lines = explode($rowDelimiter, trim($content));
        $lines = array_filter($lines);
        $lines = array_map('trim', $lines);

        if (count($lines) < 2) {
            $data['result'] = Text::_('PLG_CFI_IMPORT_EMPTY');
            Log::add(json_encode($data), Log::ERROR);
            $this->_printJson($data['result']);
        }

        // get columns
        $columns = str_getcsv($lines[0], ';');

        if ((array_search('articleid', $columns) === false) || (array_search('articletitle', $columns) === false)) {
            $data['result'] = Text::_('PLG_CFI_IMPORT_NO_COLUMN');
            Log::add(json_encode($data), Log::ERROR);
            $this->_printJson($data['result']);
        }
        unset($lines[0]);

        // set reserved name's of columns
        $reservedColumns = [
            'articleid',
            'articlecat',
            'articletitle',
            'articlelang',
            'articleintrotext',
            'articlefulltext'
        ];

        // data processing
        $errors = [];
        $inserts = 0;
        $updates = 0;
        $continues = 0;

        $fieldModel = BaseDatabaseModel::getInstance('Field', 'FieldsModel', ['ignore_request' => true]);

        Table::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_content/tables/');
        Form::addFormPath(JPATH_ADMINISTRATOR . '/components/com_content/models/forms');
        Form::addFieldPath(JPATH_ADMINISTRATOR . '/components/com_content/models/fields');

        set_time_limit(0);

        foreach ($lines as $strNum => $str) {
            // get string in file
            $fieldsData = str_getcsv($str, ';');

            // check count columns
            if (count($fieldsData) != count($columns)) {
                $errors[$strNum + 1] = Text::_('PLG_CFI_IMPORT_COLUMN_EXCEPT');
                $continues++;
                continue;
            }

            // column association
            $articleData = [];
            foreach ($columns as $key => $column) {
                if (in_array($column, $reservedColumns)) {
                    $articleData[$column] = $fieldsData[$key];
                } else {
                    $fieldsData[$column] = $fieldsData[$key];
                }
                unset($fieldsData[$key]);
            }

            // get missing article values
            $articleData['articlecat'] = array_key_exists('articlecat', $articleData) && in_array($articleData['articlecat'], $categories) ? $articleData['articlecat'] : $categories[0];
            $articleData['articlelang'] = array_key_exists('articlelang', $articleData) ? $articleData['articlelang'] : '*';
            $articleData['articleintrotext'] = array_key_exists('articleintrotext', $articleData) ? $articleData['articleintrotext'] : '';
            $articleData['articlefulltext'] = array_key_exists('articlefulltext', $articleData) ? $articleData['articlefulltext'] : '';

            // get article instance
            $model = BaseDatabaseModel::getInstance('Article', 'ContentModel');

            $article = [];
            $isNewArticle = true;
            $state = 1;
            if ($articleData['articleid'] > 0) {
                $article = $model->getItem((int)$articleData['articleid']);

                if (!$article->id) {
                    unset($article);
                    $state = 0;
                    $errors[$strNum + 1] = Text::sprintf('PLG_CFI_IMPORT_LOAD_ARTICLE', $articleData['articleid']);
                } else {
                    $isNewArticle = false;
                    $article = (array)$article;
                    unset($article[array_key_first($article)]);
                    if (isset($article['tags'])) {
                        $article['tags'] = explode(',', $article['tags']->tags);
                    }

                    // set new data on existing article item
                    $article['title'] = $articleData['articletitle'];
                    $article['introtext'] = $articleData['articleintrotext'];
                    $article['fulltext'] = $articleData['articlefulltext'];
                }
            }

            if ($isNewArticle) {
                //set data on new article item
                $article['id'] = 0;
                $article['title'] = $articleData['articletitle'];
                $article['alias'] = OutputFilter::stringURLSafe($article['title']);
                $article['introtext'] = $articleData['articleintrotext'];
                $article['fulltext'] = $articleData['articlefulltext'];
                $article['catid'] = $articleData['articlecat'];
                $article['language'] = $articleData['articlelang'];
                $article['created'] = Factory::getDate()->toSql();
                $article['created_by'] = explode(':', $this->_user)[0];
                $article['state'] = $state;
                $article['access'] = $this->_app->get('access', 1);
                $article['metadata'] = json_decode('{"robots":"","author":"","rights":"","xreference":""}', true);
                $article['images'] = json_decode('{"image_intro":"","float_intro":"","image_intro_alt":"","image_intro_caption":"","image_fulltext":"","float_fulltext":"","image_fulltext_alt":"","image_fulltext_caption":""}', true);
                $article['urls'] = json_decode('{"urla":false,"urlatext":"","targeta":"","urlb":false,"urlbtext":"","targetb":"","urlc":false,"urlctext":"","targetc":""}', true);
                $article['attribs'] = json_decode('{"article_layout":"","show_title":"","link_titles":"","show_tags":"","show_intro":"","info_block_position":"","info_block_show_title":"","show_category":"","link_category":"","show_parent_category":"","link_parent_category":"","show_associations":"","show_author":"","link_author":"","show_create_date":"","show_modify_date":"","show_publish_date":"","show_item_navigation":"","show_icons":"","show_print_icon":"","show_email_icon":"","show_vote":"","show_hits":"","show_noauth":"","urls_position":"","alternative_readmore":"","article_page_title":"","show_publishing_options":"","show_article_options":"","show_urls_images_backend":"","show_urls_images_frontend":""}', true);
            }

            // save article item
            if ($model->save($article) === false) {
                unset($article);
                if (!empty($errors[$strNum + 1])) {
                    $errors[$strNum + 1] .= '. ' . Text::_('PLG_CFI_IMPORT_SAVE_ARTICLE');
                } else {
                    $errors[$strNum + 1] = Text::_('PLG_CFI_IMPORT_SAVE_ARTICLE');
                }
                $continues++;
                continue;
            } else {
                if (!empty($errors[$strNum + 1])) {
                    $errors[$strNum + 1] .= '. ' . Text::_('PLG_CFI_IMPORT_SAVENEW_ARTICLE');
                }
            }

            if ($isNewArticle) {
                $inserts++;

                // get ID for the new article
                $article['id'] = $model->getState($model->getName() . '.id');
            } else {
                $updates++;
            }

            // get article custom fields
            $jsFields = FieldsHelper::getFields('com_content.article', $article, true);
            foreach($jsFields as $key => $jsField) {
                $jsFields[$jsField->name] = $jsField;
                unset($jsFields[$key]);
            }

            // save field's values
            $fieldsErrors = [];
            foreach ($fieldsData as $fieldName => $fieldValue) {
                if (array_key_exists($fieldName, $jsFields)) {
                    if ($jsFields[$fieldName]->type === 'checkboxes' || in_array($jsFields[$fieldName]->type, array_keys($this->_fieldPlugins))) {
                        $decode = json_decode($fieldValue, true);
                        $fieldValue = json_last_error() === JSON_ERROR_NONE ? $decode : [$fieldValue];
                    } elseif (strpos($fieldValue, 'array::') === 0) {
                        $fieldValue = json_decode(explode('::', $fieldValue, 2)[1]);
                    }
                    if (!$fieldModel->setFieldValue($jsFields[$fieldName]->id, $article['id'], $fieldValue)) {
                        $fieldsErrors[] = $fieldName;
                    }
                }
            }
            if ($fieldsErrors) {
                $errors[$strNum + 1] = Text::sprintf('PLG_CFI_IMPORT_SAVE_FIELDS', implode(', ', $fieldsErrors));
            }

            // destroy article instance
            unset($article, $jsFields);
        }

        // show result
        $data['result'] = Text::sprintf('PLG_CFI_RESULT', $inserts + $updates, $inserts, $updates) . ($errors ? '<br>' . Text::sprintf('PLG_CFI_RESULT_ERROR', $continues) : '');
        if ($errors) {
            $data['errors'] = $errors;
        } else {
            @unlink($this->_file);
        }
        Log::add(json_encode($data), Log::INFO);
        $this->_printJson($data['result'], true);
    }

    private function _getCategories()
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('id')
            ->from('#__categories')
            ->where('extension = "com_content"')
            ->order('id');
        $db->setQuery($query);
        try {
            return $db->loadColumn();
        } catch (Exception $e) {
            return false;
        }
    }

    private function _exportData()
    {
        // log template
        $data = [
            'result' => '',
            'user' => $this->_user
        ];

        // get id category
        $catid = (int)$this->_app->input->get('cficat', 0);
        if (!$catid) {
            $data['result'] = Text::_('PLG_CFI_EXPORT_NO_CATEGORY');
            Log::add(json_encode($data), Log::ERROR);
            $this->_printJson($data['result']);
        }

        // get articles
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('id, title, language, introtext, `fulltext`')
            ->from('#__content')
            ->where('state >= 0')
            ->where('catid = ' . (int)$catid)
            ->order('id');
        $db->setQuery($query);
        try {
            $articles = $db->loadObjectList();
        } catch (Exception $e) {
            $data['result'] = Text::_('PLG_CFI_EXPORT_GET_CONTENT');
            Log::add(json_encode($data), Log::ERROR);
            $this->_printJson($data['result']);
        }

        if (!$articles) {
            $data['result'] = Text::_('PLG_CFI_EXPORT_EMPTY_CONTENT');
            Log::add(json_encode($data), Log::ERROR);
            $this->_printJson($data['result']);
        }

        // file handler
        $this->_file = Path::clean(Factory::getConfig()->get('tmp_path') . '/cfi_export_' . date('Y-m-d-H-i-s') . '.csv');
        if (($fileHandle = fopen($this->_file, 'w')) === false) {
            $data['result'] = Text::_('PLG_CFI_EXPORTfile_CREATE');
            Log::add(json_encode($data), Log::ERROR);
            $this->_printJson($data['result']);
        }

        // make columns
        $columns = [
            'articleid',
            'articlecat',
            'articletitle',
            'articlelang',
            'articleintrotext',
            'articlefulltext'
        ];
        $jsFields = FieldsHelper::getFields('com_content.article', $articles[0], true);
        foreach($jsFields as $key => $jsField) {
            $columns[] = $jsField->name;
        }
        fputcsv($fileHandle, $columns, ';');

        // processing
        foreach ($articles as $article) {
            $outItem = [];
            $outItem[] = $article->id;
            $outItem[] = $catid;
            $outItem[] = str_replace(["\n", "\r"], '', $article->title);
            $outItem[] = str_replace(["\n", "\r"], '', $article->language);
            $outItem[] = str_replace(["\n", "\r"], '', $article->introtext);
            $outItem[] = str_replace(["\n", "\r"], '', $article->fulltext);

            $jsFields = FieldsHelper::getFields('com_content.article', $article, true);
            foreach($jsFields as $jsField) {
                if ($jsField->type === 'checkboxes' || in_array($jsField->type, array_keys($this->_fieldPlugins))) {
                    $outItem[] = count($jsField->rawvalue) > 1 ? json_encode($jsField->rawvalue) : $jsField->rawvalue[0];
                } elseif (is_array($jsField->rawvalue)) {
                    $outItem[] = 'array::' . json_encode($jsField->rawvalue);
                } else {
                    $outItem[] = str_replace(["\n", "\r"], '', $jsField->rawvalue);
                }
            }
            fputcsv($fileHandle, $outItem, ';');
        }

        // save file
        fclose($fileHandle);
        unset($articles, $jsFields);

        // convert
        if ((bool) $this->_app->input->get('cficonvert', false)) {
            $contentIn = file_get_contents($this->_file);
            if ($contentIn !== false) {
                $content = mb_convert_encoding($contentIn, $this->_cp, 'UTF-8');
                if (!$content) {
                    $data['result'] = Text::_('PLG_CFI_EXPORT_ERROR_CONVERT');
                    $date['file'] = $this->_file;
                    Log::add(json_encode($data), Log::ERROR);
                    $this->_printJson($data['result']);
                }
                if (file_put_contents($this->_file, $content) === false) {
                    $data['result'] = Text::_('PLG_CFI_EXPORT_ERROR_AFTER_CONVERT');
                    $date['file'] = $this->_file;
                    Log::add(json_encode($data), Log::ERROR);
                    $this->_printJson($data['result']);
                }
            } else {
                $data['result'] = Text::_('PLG_CFI_EXPORT_ERROR_BEFORE_CONVERT');
                $date['file'] = $this->_file;
                Log::add(json_encode($data), Log::ERROR);
                $this->_printJson($data['result']);
            }
        }

        // return result
        $data['result'] = Text::_('PLG_CFI_EXPORT_SUCCESS');
        $date['file'] = $this->_file;
        Log::add(json_encode($data), Log::INFO);
        $this->_printJson($data['result'], true, ['f' => urlencode(pathinfo($this->_file, PATHINFO_BASENAME))]);

        exit;
    }

    private function _fileDownload($file)
    {
        set_time_limit(0);
        if (file_exists($file)) {
            if (ob_get_level()) {
                ob_end_clean();
            }
            header('Content-Description: File Transfer');
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename=' . basename($file));
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($file));
            return (bool) readfile($file);
        } else {
            return false;
        }
    }
}

if (!function_exists('array_key_first')) {
    function array_key_first(array $array)
    {
        foreach ($array as $key => $unused) {
            return $key;
        }
        return null;
    }
}
