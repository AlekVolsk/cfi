<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  System.cfi
 * @copyright   Copyright (C) Aleksey A. Morozov. All rights reserved.
 * @license     GNU General Public License version 3 or later; see http://www.gnu.org/licenses/gpl-3.0.txt
 */

\defined('_JEXEC') or die;

use Joomla\Registry\Registry;
use Joomla\CMS\Factory;
use Joomla\CMS\Version;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\URI\URI;
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

    private $app;
    private $appConfig;
    private $doc;
    private $user;
    private $file = null;
    private $cp;
    private $fieldPlugins;

    protected $autoloadLanguage = true;

    public function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);
        $this->initConstruct();
    }

    private function initConstruct($ajax = false)
    {
        if (Version::MAJOR_VERSION > 3) {
            $this->app       = Factory::getContainer()->get(Joomla\CMS\Application\AdministratorApplication::class);
            $this->appConfig = $this->app->getConfig();
            $user            = $this->app->getIdentity();
            $this->db        = Factory::getContainer()->get('DatabaseDriver');
        } else {
            $this->app       = Factory::getApplication('administrator');
            $this->appConfig = Factory::getConfig();
            $user            = Factory::getUser();
            $this->db        = Factory::getDbo();
        }
        $this->doc       = Factory::getDocument();

        if ($ajax) {
            $option = $this->app->input->get('option');
            $view   = $this->app->input->get('view');
            if (!($option == 'com_content' && (in_array($view, ['articles', 'featured', ''])))) {
                return;
            }
        } else {
            $this->doc->addScript(URI::root(true) . '/plugins/system/cfi/assets/cfi.js');
            $this->doc->addStyleSheet(URI::root(true) . '/plugins/system/cfi/assets/cfi.css');
        }

        $this->user = $user->id . ':' . $user->username;

        $this->cp = $this->params->get('cp', 'CP1251');

        $this->fieldPlugins = [
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
        foreach (array_keys($this->fieldPlugins) as $pluginName) {
            $multiple = $plugins[$pluginName]->params->get('multiple', -1);
            if ($multiple >= 0) {
                $this->fieldPlugins[$pluginName] = (int)$multiple;
            }
        }

        BaseDatabaseModel::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_fields/models', 'FieldsModel');
        BaseDatabaseModel::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_content/models/', 'ContentModel');
    }

    public function onBeforeRender()
    {
        if ($this->doc->getType() != 'html' || !$this->app->isClient('administrator')) {
            return;
        }

        $option = $this->app->input->get('option');
        $view   = $this->app->input->get('view');
        if (!($option == 'com_content' && (in_array($view, ['articles', 'featured', ''])))) {
            return;
        }

        $toolbar = new FileLayout('toolbar', Path::clean(JPATH_PLUGINS . '/system/cfi/layouts'));
        ToolBar::getInstance('toolbar')->appendButton('Custom', $toolbar->render([]), 'cfi');

        return true;
    }

    public function onAfterRender()
    {
        if ($this->doc->getType() != 'html' || !$this->app->isClient('administrator')) {
            return;
        }

        $option = $this->app->input->get('option');
        $view   = $this->app->input->get('view');
        if (!($option == 'com_content' && (in_array($view, ['articles', 'featured', ''])))) {
            return;
        }

        $html = $this->app->getBody();

        if (strpos($html, '</head>') !== false) {
            list($head, $content) = explode('</head>', $html, 2);
        } else {
            $content = $html;
        }

        if (empty($content)) {
            return false;
        }

        $query = $this->db->getQuery(true)
            ->select('id, title')
            ->from('#__categories')
            ->where('extension = "com_content"')
            ->order('title');
        $this->db->setQuery($query);
        try {
            $categories = $this->db->loadObjectList();
        } catch (Exception $e) {
            $categories = [];
        }

        $well = new FileLayout('well', Path::clean(JPATH_PLUGINS . '/system/cfi/layouts'));
        $matches = [];
        preg_match('#id="j-main-container" (\w+)(.*?)>#i', $content, $matches);
        if ($matches && $matches[0]) {
            $wellParams = [
                'cp' => $this->cp,
                'categories' => $categories,
                'showdesc' => $this->params->get('showdesc', 1)
            ];
            $content = str_replace($matches[0], $matches[0] . $well->render($wellParams), $content);
            $html = isset($head) ? ($head . '</head>' . $content) : $content;
            $this->app->setBody($html);
            return true;
        }

        return;
    }

    public function onAjaxCfi()
    {
        Log::addLogger(['textfile' => 'cfi.php', 'text_entry_format' => "{DATETIME}\t{PRIORITY}\t{MESSAGE}"], Log::ALL);

        $this->initConstruct(true);

        $state = $this->app->input->get('cfistate', '');

        if (!Session::checkToken($state == 'download' ? 'get' : 'post')) {
            $data = [
                'result' => Text::_('JINVALID_TOKEN'),
                'user' => $this->user,
                'file' => $this->app->input->files->getArray(),
                'get' => $this->app->input->get->getArray(),
                'post' => $this->app->input->post->getArray()
            ];
            Log::add(json_encode($data), Log::ERROR);
            $this->printJson($data['result']);
        }

        if ($state == 'import') {
            $this->checkFile($this->app->input->files->get('cfifile'));
            $this->importData();
        }

        if ($state == 'export') {
            $this->exportData();
        }

        if ($state == 'download') {
            $this->file = $this->app->input->get('f', '');
            if ($this->file) {
                $this->file = Path::clean($this->appConfig->get('tmp_path') . '/' . urldecode($this->file));
                $this->fileDownload($this->file);
                @unlink($this->file);
            }
        }
    }

    private function printJson($message = '', $result = false, $custom = [])
    {
        $custom['result'] = $result;
        $custom['message'] = $message;
        echo json_encode($custom);
        exit;
    }

    private function checkFile($file)
    {
        $data = [
            'result' => '',
            'user' => $this->user,
            'file' => $file
        ];

        if (is_array($file) && count($file)) {
            if ($file['error'] != 0) {
                $data['result'] = Text::_('PLG_CFIfile_ERROR');
                Log::add(json_encode($data), Log::ERROR);
                $this->printJson($data['result']);
            }

            if (!$file['size']) {
                $data['result'] = Text::_('PLG_CFIfile_SIZE');
                Log::add(json_encode($data), Log::ERROR);
                $this->printJson($data['result']);
            }

            if (pathinfo($file['name'], PATHINFO_EXTENSION) !== 'csv') {
                $data['result'] = Text::_('PLG_CFIfile_TYPE');
                Log::add(json_encode($data), Log::ERROR);
                $this->printJson($data['result']);
            }

            $this->file = Path::clean($this->appConfig->get('tmp_path') . '/cfi_' . date('Y-m-d-H-i-s') . '.csv');
            if (!@move_uploaded_file($file['tmp_name'], $this->file)) {
                $data['result'] = Text::_('PLG_CFIfile_MOVE');
                Log::add(json_encode($data), Log::ERROR);
                $this->printJson($data['result']);
            }

            return true;
        }

        $data['result'] = Text::_('PLG_CFIfile_NOTHING');
        Log::add(json_encode($data), Log::ERROR);
        $this->printJson($data['result']);
    }

    private function importData()
    {
        // log template
        $data = [
            'result' => '',
            'user' => $this->user,
            'file' => $this->file
        ];

        // get categories
        $categories = $this->getCategories();
        if (!$categories) {
            $data['result'] = Text::_('PLG_CFI_IMPORT_GET_CATEGORIES');
            Log::add(json_encode($data), Log::ERROR);
            $this->printJson($data['result']);
        }

        // get file content
        $content = trim(file_get_contents($this->file));

        // convert to UTF-8
        $isConvert = (int) $this->app->input->get('cficonvert', 0);

        if ($isConvert > 0) {
            $content = mb_convert_encoding($content, 'UTF-8', $this->cp);
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
            $this->printJson($data['result']);
        }

        // get columns
        $columns = str_getcsv($lines[0], ';');

        if ((array_search('articleid', $columns) === false) || (array_search('articletitle', $columns) === false)) {
            $data['result'] = Text::_('PLG_CFI_IMPORT_NO_COLUMN');
            Log::add(json_encode($data), Log::ERROR);
            $this->printJson($data['result']);
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

        BaseDatabaseModel::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_content/models/', 'ContentModel');
        Table::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_content/tables/');
        if (Version::MAJOR_VERSION > 3) {
            Form::addFormPath(JPATH_ADMINISTRATOR . '/components/com_content/forms');
        } else {
            Form::addFormPath(JPATH_ADMINISTRATOR . '/components/com_content/models/forms');
            Form::addFormPath(JPATH_ADMINISTRATOR . '/components/com_content/model/form');
            Form::addFieldPath(JPATH_ADMINISTRATOR . '/components/com_content/models/fields');
            Form::addFieldPath(JPATH_ADMINISTRATOR . '/components/com_content/model/field');
        }

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
                $article['id']         = 0;
                $article['title']      = $articleData['articletitle'];
                $article['alias']      = OutputFilter::stringURLSafe($article['title']);
                $article['introtext']  = $articleData['articleintrotext'];
                $article['fulltext']   = $articleData['articlefulltext'];
                $article['catid']      = $articleData['articlecat'];
                $article['language']   = $articleData['articlelang'];
                $article['featured']   = 0;
                $article['created']    = Factory::getDate()->toSql();
                $article['created_by'] = explode(':', $this->user)[0];
                $article['state']      = $state;
                $article['access']     = $this->app->get('access', 1);
                $article['metadata']   = json_decode('{"robots":"","author":"","rights":"","xreference":""}', true);
                $article['images']     = json_decode('{"image_intro":"","float_intro":"","image_intro_alt":"","image_intro_caption":"","image_fulltext":"","float_fulltext":"","image_fulltext_alt":"","image_fulltext_caption":""}', true);
                $article['urls']       = json_decode('{"urla":false,"urlatext":"","targeta":"","urlb":false,"urlbtext":"","targetb":"","urlc":false,"urlctext":"","targetc":""}', true);
                $article['attribs']    = json_decode('{"article_layout":"","show_title":"","link_titles":"","show_tags":"","show_intro":"","info_block_position":"","info_block_show_title":"","show_category":"","link_category":"","show_parent_category":"","link_parent_category":"","show_associations":"","show_author":"","link_author":"","show_create_date":"","show_modify_date":"","show_publish_date":"","show_item_navigation":"","show_icons":"","show_print_icon":"","show_email_icon":"","show_vote":"","show_hits":"","show_noauth":"","urls_position":"","alternative_readmore":"","article_page_title":"","show_publishing_options":"","show_article_options":"","show_urls_images_backend":"","show_urls_images_frontend":""}', true);
            }

            // article form
            $form = $model->getForm($article, true);
            $errs = [];
            if (!$form) {
                foreach ($model->getErrors() as $error) {
                    $errs[] = ($error instanceof Exception) ? $error->getMessage() : $error;
                }
                if (!empty($errors[$strNum + 1])) {
                    $errors[$strNum + 1] .= '. ' . Text::_('PLG_CFI_IMPORT_SAVE_ARTICLE') . ': ' . implode('; ', $errs);
                } else {
                    $errors[$strNum + 1] = Text::_('PLG_CFI_IMPORT_SAVE_ARTICLE') . ': ' . implode('; ', $errs);
                }
                unset($model, $article, $errs);
                $continues++;
                continue;
            }

            // save article item
            $this->app->input->set('task', 'save');
            if ($model->save($article) === false) {
                foreach ($model->getErrors() as $error) {
                    $errs[] = ($error instanceof Exception) ? $error->getMessage() : $error;
                }
                if (!empty($errors[$strNum + 1])) {
                    $errors[$strNum + 1] .= '. ' . Text::_('PLG_CFI_IMPORT_SAVE_ARTICLE') . ': ' . implode('; ', $errs);
                } else {
                    $errors[$strNum + 1] = Text::_('PLG_CFI_IMPORT_SAVE_ARTICLE') . ': ' . implode('; ', $errs);
                }
                unset($model, $article, $errs);
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
            foreach ($jsFields as $key => $jsField) {
                $jsFields[$jsField->name] = $jsField;
                unset($jsFields[$key]);
            }

            // save field's values
            $fieldsErrors = [];
            foreach ($fieldsData as $fieldName => $fieldValue) {
                if (array_key_exists($fieldName, $jsFields)) {
                    if ($jsFields[$fieldName]->type === 'checkboxes' || in_array($jsFields[$fieldName]->type, array_keys($this->fieldPlugins))) {
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
            @unlink($this->file);
        }
        Log::add(json_encode($data), Log::INFO);
        $this->printJson($data['result'], true);
    }

    private function getCategories()
    {
        $query = $this->db->getQuery(true)
            ->select('id')
            ->from('#__categories')
            ->where('extension = "com_content"')
            ->order('id');
        $this->db->setQuery($query);
        try {
            return $this->db->loadColumn();
        } catch (Exception $e) {
            return false;
        }
    }

    private function exportData()
    {
        // log template
        $data = [
            'result' => '',
            'user' => $this->user
        ];

        // get id category
        $catid = (int)$this->app->input->get('cficat', 0);
        if (!$catid) {
            $data['result'] = Text::_('PLG_CFI_EXPORT_NO_CATEGORY');
            Log::add(json_encode($data), Log::ERROR);
            $this->printJson($data['result']);
        }

        // get articles
        $query = $this->db->getQuery(true)
            ->select('id, title, language, introtext, `fulltext`')
            ->from('#__content')
            ->where('state >= 0')
            ->where('catid = ' . (int)$catid)
            ->order('id');
        $this->db->setQuery($query);
        try {
            $articles = $this->db->loadObjectList();
        } catch (Exception $e) {
            $data['result'] = Text::_('PLG_CFI_EXPORT_GET_CONTENT');
            Log::add(json_encode($data), Log::ERROR);
            $this->printJson($data['result']);
        }

        if (!$articles) {
            $data['result'] = Text::_('PLG_CFI_EXPORT_EMPTY_CONTENT');
            Log::add(json_encode($data), Log::ERROR);
            $this->printJson($data['result']);
        }

        // file handler
        $this->file = Path::clean($this->appConfig->get('tmp_path') . '/cfi_export_' . date('Y-m-d-H-i-s') . '.csv');
        if (($fileHandle = fopen($this->file, 'w')) === false) {
            $data['result'] = Text::_('PLG_CFI_EXPORTfile_CREATE');
            Log::add(json_encode($data), Log::ERROR);
            $this->printJson($data['result']);
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
        foreach ($jsFields as $jsField) {
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
            foreach ($jsFields as $jsField) {
                if ($jsField->type === 'checkboxes' || in_array($jsField->type, array_keys($this->fieldPlugins))) {
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
        if ((bool) $this->app->input->get('cficonvert', false)) {
            $contentIn = file_get_contents($this->file);
            if ($contentIn !== false) {
                $content = mb_convert_encoding($contentIn, $this->cp, 'UTF-8');
                if (!$content) {
                    $data['result'] = Text::_('PLG_CFI_EXPORT_ERROR_CONVERT');
                    $date['file'] = $this->file;
                    Log::add(json_encode($data), Log::ERROR);
                    $this->printJson($data['result']);
                }
                if (file_put_contents($this->file, $content) === false) {
                    $data['result'] = Text::_('PLG_CFI_EXPORT_ERROR_AFTER_CONVERT');
                    $date['file'] = $this->file;
                    Log::add(json_encode($data), Log::ERROR);
                    $this->printJson($data['result']);
                }
            } else {
                $data['result'] = Text::_('PLG_CFI_EXPORT_ERROR_BEFORE_CONVERT');
                $date['file'] = $this->file;
                Log::add(json_encode($data), Log::ERROR);
                $this->printJson($data['result']);
            }
        }

        // return result
        $data['result'] = Text::_('PLG_CFI_EXPORT_SUCCESS');
        $date['file'] = $this->file;
        Log::add(json_encode($data), Log::INFO);
        $this->printJson($data['result'], true, ['f' => urlencode(pathinfo($this->file, PATHINFO_BASENAME))]);

        exit;
    }

    private function fileDownload($file)
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
