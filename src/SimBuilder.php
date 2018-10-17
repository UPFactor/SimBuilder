<?php
namespace SimBuilder;

/**
 * Объект управления сборками
 *
 * Class Environment
 * @package SimBuilder
 */
class Environment {
    const version = '2.0.0';

    /**
     * @var string Путь до дирректориии с конфигурациями сборки
     */
    private $_config_source;

    /**
     * @var array Массив зарегистрированных сборок
     */
    private $_bundles;

    /**
     * Environment constructor.
     */
    function __construct(){
        $this->_config_source = __DIR__.'/Config/bundles.json';
        try {
            if (File::exists($this->_config_source)) {
                $this->_bundles = json_decode(File::get_content($this->_config_source), true);
                if ($this->_bundles === null) {
                    $this->_bundles = array();
                    File::put_content($this->_config_source, '{}');
                }
            } else {
                $this->_bundles = array();
                File::create($this->_config_source, '{}');
            }
        } catch (Exception $error){
            $error->getError('Initializing bundles manager');
        }
    }

    /**
     * Создает новую сборку
     *
     * @param $name
     * @param string $directory
     * @param array $config
     * @return Bundle
     */
    public function create($name, $directory='', array $config = array()){
        //Контроль данных
        try {
            $this->checkBundleName($name);
            if (!is_string($directory)) throw new Exception('Incorrect directory link');
            if (empty($config)) throw new Exception('Bundle configurations is empty');
            if (key_exists($name, $this->_bundles)) throw new Exception('The "' . $name . '" bundle exists');
        } catch (Exception $error){
            $error->getError('Creating bundle');
        }

        //Фиксация информации о созданной сборке
        if (empty($directory)) {
            $directory = __DIR__.'/Config';
        } else {
            Dir::create($directory);
        }
        $bundle = new Bundle($directory.'/'.$name .'/', $config);
        $this->_bundles[$name] = $directory.'/'.$name .'/';
        try {
            File::put_content($this->_config_source, $this->_bundles);
        } catch (Exception $error){
            $error->getError('Creating a bundle');
        }

        return $bundle;
    }

    /**
     * Импорт информации о существующей сборке
     *
     * @param $name
     * @param $directory
     * @return Bundle
     */
    public function import($name, $directory){
        try {
            $this->checkBundleName($name);
            if (key_exists($name, $this->_bundles)) throw new Exception('The "' . $name . '" bundle exists');
            $directory = Dir::realpath($directory);
            File::exists($directory . '/config.json', true);
        } catch (Exception $error){
            $error->getError('Import bundle');
        }

        $this->_bundles[$name] = $directory.'/';

        try {
            File::put_content($this->_config_source, $this->_bundles);
        } catch (Exception $error){
            $error->getError('Creating a bundle');
        }

        return $this->get($name);
    }

    /**
     * Удаляет сборку по имени (ключу)
     *
     * @param $bundleName
     * @return $this
     */
    public function remove($bundleName){
        if (key_exists($bundleName, $this->_bundles)){
            unset($this->_bundles[$bundleName]);
            try {
                File::put_content($this->_config_source, $this->_bundles);
            } catch (Exception $error){
                $error->getError('Removing bundle');
            }
        }
        return $this;
    }

    /**
     * Возвращает объект сборки по имени (ключу)
     *
     * @param $name
     * @return Bundle
     */
    public function get($name){
        if (!key_exists($name, $this->_bundles)) Dialog::error('Bundle "' . $name . '" not found');
        return new Bundle($this->_bundles[$name]);
    }

    /**
     * Возвращает массив зарегистрированных сборок
     *
     * @return array
     */
    public function getList(){
        return $this->_bundles;
    }

    /**
     * Выполняет проверку на корректное имя (ключа) сборки
     *
     * @param $name
     * @throws Exception
     */
    public function checkBundleName($name){
        if (empty($name) or !preg_match('/[a-z0-9-_]/is',$name)) {
            throw new Exception('Incorrect bundle name');
        }
    }
}

/**
 * Конфигурации сборки
 *
 * Class DataBundleConfig
 * @package SimBuilder
 */
class DataBundleConfig extends Data {

    final protected function setDefaultValues(){
        return array(
            'compression' => false,
            'anchor_css' => 'css-block',
            'anchor_js' => 'js-block',
            'pages' => array(),
            'ignore' => array()
        );
    }

    final protected function setFormatValues(){
        return array(
            'compression' => array(
                'type' => 'boolean',
            ),
            'sources_pages' => array(
                'type' => 'string',
                'required' => true,
                'prepare' => function($value){
                    if (realpath($value) === false){
                        Dir::create($value);
                        Dialog::notice('Created directory for source pages — "'.$value.'"');
                    }
                    return $value;
                }
            ),
            'sources_blocks' => array(
                'type' => 'string',
                'required' => true,
                'prepare' => function($value){
                    if (realpath($value) === false){
                        Dir::create($value);
                        Dialog::notice('Created directory for source blocks — "'.$value.'"');
                    }
                    return $value;
                }
            ),
            'attachable_files' => array(
                'type' => 'string',
                'required' => true,
                'prepare' => function($value){
                    if (realpath($value) === false){
                        Dir::create($value);
                        Dialog::notice('Created directory for attachable files — "'.$value.'"');
                    }
                    return $value;
                }
            ),
            'anchor_css' => array(
                'type' => 'string',
                'required' => true,
            ),
            'anchor_js' => array(
                'type' => 'string',
                'required' => true,
            ),
            'pages' => array(
                'type' => 'array',
                'prepare' => function($value){
                    $value = array_filter($value);
                    foreach ($value as $k => $item) {
                        if (!is_string($item) or !preg_match('/^\s*[a-z-0-9-]+\s*$/is',$item)) {
                            throw new Exception('Incorrect page stack format in bundle configuration');
                        }
                        $value[$k] = trim($item);
                    }
                    return $value;
                }
            ),
            'ignore' => array(
                'type' => 'array',
                'prepare' => function($value){
                    $value = array_filter($value);
                    foreach ($value as $k => $v) {
                        if (!is_string($v)) {
                            throw new Exception('Incorrect ignore stack format in bundle configuration');
                        }
                        $value[$k] = trim($v);
                    }
                    return $value;
                },
            )
        );
    }

    final public function addPages(array $pages = array()){
        $pages = $this->validation('pages', $pages);
        foreach ($pages as $page){
            if (in_array($page, $this->data['pages'])) continue;
            $this->data['pages'][] = $page;
        }
        return $this;
    }

    final public function removePages(array $pages = array()){
        foreach ($this->data['pages'] as $k => $page){
            if (in_array($page, $pages)) unset($this->data['pages'][$k]);
        }
        $this->data['pages'] = array_values($this->data['pages']);
        return $this;
    }

    final public function addIgnore(array $ignore = array()){
        $ignore = $this->validation('ignore',$ignore);
        foreach ($ignore as $item){
            if (in_array($item, $this->data['ignore'])) continue;
            $this->data['ignore'][] = $item;
        }
        return $this;
    }

    final public function removeIgnore(array $ignore = array()){
        foreach ($this->data['ignore'] as $k => $item){
            if (in_array($item, $ignore)) unset($this->data['ignore'][$k]);
        }
        $this->data['ignore'] = array_values($this->data['ignore']);
        return $this;
    }

}

/**
 * Объект сборки
 *
 * Class Bundle
 * @package SimBuilder
 */
class Bundle {

    /**
     * @var string Директория сборки
     */
    private $_path;

    /**
     * @var array Индекс сборки
     */
    private $_index = array();

    /**
     * @var array Буфер скомпилированных блоков
     */
    private $_compiled_blocks = array();

    /**
     * @var array Буфер неактульных блоков
     */
    private $_irrelevant_blocks = array();

    /**
     * @var DataBundleConfig
     */
    public $config;

    /**
     * bundle constructor.
     * @param $path
     * @param array $config
     */
    function __construct($path, array $config = array()){

        if (empty($config)) {
            try {
                $this->_path = File::realpath($path);
                File::exists($this->_path . '/config.json', true);
                $this->config = new DataBundleConfig(json_decode(File::get_content($this->_path . '/config.json'), true));
                $this->config->bindToFile($this->_path . '/config.json');

                $this->_index = json_decode(File::get_content($this->_path . '/index.json'), true);
                if ($this->_index === null) {
                    $this->_index = array();
                    File::put_content($this->_path . '/index.json', '{}');
                }
            } catch (Exception $error){
                $error->getError('Initializing a bundle');
            }
        } else {
            try {
                $this->config = new DataBundleConfig($config);

                if (!File::exists($path)) {
                    Dir::create($path);
                    $this->_path = File::realpath($path);
                } else {
                    $this->_path = File::realpath($path);
                }

                File::create($this->_path . '/config.json', $this->config->getJSON());
                File::create($this->_path . '/index.json', '{}');
                Dir::create($this->_path . '/blocks/');
                Dir::create($this->_path . '/pages/');

                $this->config->bindToFile($this->_path . '/config.json');
                $this->_index = array();
            } catch (Exception $error){
                $error->getError('Creating a bundle');
            }
        }
    }

    /**
     * @return $this
     */
    public function compile(){
        $pages = array();

        try {
            $pages = $this->config->get('pages');
        } catch (Exception $error) {
            $error->getError('Compilation of page in bundle');
        }

        foreach ($pages as $page_name) {
            try {
                //Проверяем актульаность блока типа pages (блок который не имееет родителя)
                $page_info = $this->getIndexInfo('pages:'.$page_name, array('actual'));
                if (!is_array($page_info) or $page_info['actual'] !== true) {
                    $this->CompileBlock('pages', $page_name);
                    Dialog::message("   ".'Page "'.$page_name.'" compiled');
                }
            } catch (Exception $error) {
                $error->getError('Compilation of page "' . $page_name . '" in bundle');
            }
        }

        //Отчищаем массив скомпилированных блоков
        $this->_compiled_blocks = array();

        //Отчищаем массив неактуальных блоков
        $this->_irrelevant_blocks = array();

        //Компиляция страниц карты навигации
        $this->compileMap();

        try {
            File::put_content($this->_path . '/index.json', $this->_index);
        } catch (Exception $error){
            $error->getError('Saving the bundle index');
        }

        return $this;
    }

    public function getIndex(){
        return $this->_index;
    }

    /**
     * @return $this
     */
    public function resetIndex(){
        try {
            $this->_index = array();
            File::put_content($this->_path . '/index.json', '{}');
        } catch (Exception $error){
            $error->getError('Resetting the bundle index');
        }

        return $this;
    }

    public function clearCompilation(){
        Dir::clear($this->_path . '/pages');
        Dir::clear($this->_path . '/blocks');
    }

    public function getPageInfo($name, array $options = array()){
        $result = $this->getIndexInfo('pages:'.$name, $options);
        if ($result === false) Dialog::error('To obtain information, you must compile the bundle');
        return $result;
    }

    public function getBlockInfo($name, array $options = array()){
        $result = $this->getIndexInfo('blocks:'.$name, $options);
        if ($result === false) Dialog::error('To obtain information, you must compile the bundle');
        return $result;
    }

    /**
     * @param $name
     * @param array $options
     * @return array|bool
     */
    protected function getIndexInfo($name, array $options = array()){
        if (empty($name) or !is_string($name)) Dialog::error('Incorrect block name — "' . $name . '", possible string value');

        if (!key_exists($name, $this->_index)) {
            if(!in_array($name, $this->_irrelevant_blocks)){
                $this->_irrelevant_blocks[] = $name;
            }
            return false;
        }

        $index_item = $this->_index[$name];

        try {

            //Получаем конфигурации сборки
            $bundle_config = $this->config->getArray();

            if (empty($options) or in_array('actual', $options)) {
                //Проверка актуальности блока
                $block_is_actual = true;
                //Если блок присутствует в списке неактуальных
                if (in_array($name, $this->_irrelevant_blocks)){
                    $block_is_actual = false;
                } elseif(
                    //Если текущий параметр компрессии соответствует параметру скомпилированного блока
                    $index_item['compilation']['compression'] == $bundle_config['compression']
                    and
                    //Если директория в которую скомпилирован блок существует
                    file_exists($index_item['compilation']['path'])
                    and
                    //Если хеш исходников блока совпадает
                    Procedure::getBlockStamp(
                        $index_item['path']['block'],
                        $index_item['path']['template']
                    ) == $index_item['stamp']
                    and
                    //Если хеш директории компиляции блока совпадает
                    $index_item['compilation']['stamp'] == Dir::stamp($index_item['compilation']['path'])
                ) {

                    //Проверка актуальности зависимостей блока
                    foreach ($index_item['dependencies'] as $index_d) {

                        //Исключаем вставку анкоров
                        if (in_array($index_d, array($bundle_config['anchor_css'], $bundle_config['anchor_js']))) {
                            continue;
                        }

                        $index_d = 'blocks:'.$index_d;

                        //Если зависимый блок присутствует в списке неактуальных
                        if(in_array($index_d, $this->_irrelevant_blocks)){
                            $block_is_actual = false;
                            break;
                        }

                        //Если зависимый блок отсутствует в индексе
                        if (!key_exists($index_d, $this->_index)) {
                            $block_is_actual = false;
                            $this->_irrelevant_blocks[] = $index_d;
                            break;
                        }

                        //Если текущий параметр компрессии не соответствует параметру скомпилированного зависимого блока
                        if ($this->_index[$index_d]['compilation']['compression'] != $bundle_config['compression']) {
                            $block_is_actual = false;
                            $this->_irrelevant_blocks[] = $index_d;
                            break;
                        }

                        //Если директория в которую скомпилирован зависимый блок не существует
                        if (!file_exists($this->_index[$index_d]['compilation']['path'])){
                            $block_is_actual = false;
                            $this->_irrelevant_blocks[] = $index_d;
                            break;
                        }

                        //Если хеш исходников зависимого блока не совпадает
                        if (
                            Procedure::getBlockStamp(
                                $this->_index[$index_d]['path']['block'],
                                $this->_index[$index_d]['path']['template']
                            ) !== $this->_index[$index_d]['stamp']
                        ) {
                            $block_is_actual = false;
                            $this->_irrelevant_blocks[] = $index_d;
                            break;
                        }

                        //Если хеш директории компиляции зависимого блока не совпадает
                        if ($this->_index[$index_d]['compilation']['stamp'] != Dir::stamp($this->_index[$index_d]['compilation']['path'])) {
                            $block_is_actual = false;
                            $this->_irrelevant_blocks[] = $index_d;
                            break;
                        }

                    }
                } else {
                    $block_is_actual = false;
                    $this->_irrelevant_blocks[] = $name;
                }

                $index_item['actual'] = $block_is_actual;
            }

        } catch (Exception $error){
            $error->getError('Getting information about block "'.$name.'"');
        }

        if (empty($options)){
            return $index_item;
        } else {
            $option_index_item = array();
            foreach ($options as $k){
                if (key_exists($k, $index_item)){
                    $option_index_item[$k] = $index_item[$k];
                }
            }
            return $option_index_item;
        }
    }

    /**
     * @param $type
     * @param $name
     * @param string $parent_name
     * @param array $mixins
     * @return Block
     * @throws Exception
     */
    protected function compileBlock($type, $name, $parent_name='', array $mixins = array()){

        //Контроль входящих переменных
        if (empty($name) or !is_string($name)) throw new Exception('Incorrect name "'.$name.'" for block, possible string value');
        if (!in_array($type, array('pages','blocks'))) throw new Exception('Incorrect type "'.$type.'" for block "'.$name.'", possible values "pages" or "blocks"');
        if (!is_string($parent_name)) throw new Exception('Incorrect name "'.$parent_name.'" of the parent block for block "'.$name.'", possible string value');

        //Регистрируем запись в индексе
        $this->_index[$type.':'.$name]['type'] = $type;

        //Получаем конфигурации сборки
        $bundle_config = $this->config->getArray();

        //Отделяем название шаблона от имени блока
        $ar_name = explode('_', $name);

        //Получаем объект блока
        $block = new Block(
            $bundle_config['sources_'.$type].'/'.array_shift($ar_name), //Путь исходника блока
            $ar_name, //Массив состоящий из элементов пути к требуемому шаблону блока
            array(
                'compression' => $bundle_config['compression'],
                'ignore' => $bundle_config['ignore'],
                'attachable_files' => $bundle_config['attachable_files']
            )
        );
        unset($ar_name);

        //Выполняем компиляцию блока
        $block->compile($mixins);
        $block->config->set('label',(($block->is_mixed === false) ? '' : $parent_name));

        //Объединяем миксины
        $mixins = Procedure::mergeMixins($mixins, $block->config->get('mixins'));

        //Компилируем зависимости шаблона
        if (!empty($block->dependencies)){
            foreach ($block->dependencies as $item){
                if (in_array($item, array($bundle_config['anchor_css'], $bundle_config['anchor_js']))){
                    continue;
                }

                //Объединяем дочерний блок в родительский
                $block->merge($this->compileBlock('blocks', $item, $block->name, $mixins));
            }
        }

        //Заменяем анкоры css, js на пути к соответствующим файлам для страниц
        if ($type == 'pages') {
            $block->replaceByDependencyKey(
                array(
                    $bundle_config['anchor_css'],
                    $bundle_config['anchor_js']
                ),
                array(
                    '<link href="'.$block->name.'.css?'.time().'" rel="stylesheet"/>',
                    '<script src="'.$block->name.'.js?'.time().'"></script>'
                )
            );
        }

        //Cохраняем скомпилированный блок
        $compilation_path = $block->save($this->_path . '/'.$type);

        //Записывае инофрмацию о блоке в индекс
        $this->_index[$type.':'.$name] = array(
            'id' => $name, //Идентификатор блока
            'name' => $block->name, //Имя блока
            'type' => $type, //Тип блока (pages, blocks)
            'config' => $block->config->getArray(), //Конфигурации блока установленные при компиляции
            'stamp' => $block->getStamp(), //Хеш исходных файлов блока
            'actual' => null, //Метка актуальности блока
            'path' => array(
                'block' => $block->path, //Путь до корневой директории исходного блока
                'template' => $block->workspace_path //Путь до шаблона исходного блока
            ),
            'parent' => $parent_name, //Родительский блок
            'dependencies' => $block->dependencies, //Массив зависимостей блока
            'mixins' => array(
                'environment' => $mixins, //Миксины окружения
                'used' => $block->used_mixins //Миксины которые были применены к блоку при компиляции
            ),
            'compilation' => array(
                'time' => date('c'), //Время последней компиляции
                'stamp' => Dir::stamp($compilation_path), //Хеш скомпилированных файлов блока
                'path' => $compilation_path, //Путь до каталога в который был скомпилирован блок
                'compression' => $bundle_config['compression'] //Метка сжатия блока
            )
        );

        return $block;
    }

    protected function compileMap(){
        $content = '';
        $style = preg_replace('/[\n\r\t]|\s{2,}/', '','
                table {
                    width: auto;
                    min-width: 50%;
                    margin: 16px;
                    border-collapse: collapse;
                }
                td {
                    color: #333333;
                    font-size: 14px;
                    vertical-align: top;
                    padding: 12px 0;
                    border-bottom: 1px solid #e6e6e6;
                }
                td a {
                    color: #1976D2;
                    text-decoration: none;
                }
            ');

        foreach ($this->_index as $item) {
            if ($item['type'] !== 'pages') continue;
            $id = $item['id'];
            $name  = empty($item['config']['name']) ? $item['id'] : $item['config']['name'];
            $description = empty($item['config']['description']) ? '—' : $item['config']['description'];
            $date = empty($item['compilation']['time']) ? '—' : date('d.m.Y H:i:s', strtotime($item['compilation']['time']));
            $link = str_replace($this->_path, '.', $item['compilation']['path']).'/'.$item['name'].'.html';
            $content.= '<tr><td><a href="'.$link.'" target="_blank">'.$name.' (ID: '.$id.')</a><br />'.$description.'</td><td>Compiled: '.$date.'</td></tr>';
        }

        try {
            File::create($this->_path.'/index.html', '<!DOCTYPE html><html><head><style>' . $style . '</style></head><body><table>' . $content . '</table></body></html>');
        } catch (Exception $error){
            $error->getError('Compiling pages of the navigation map');
        }
    }
}

/**
 * Конфигурации блока
 *
 * Class DataBlockConfig
 * @package SimBuilder
 */
class DataBlockConfig extends Data {

    protected function setDefaultValues(){
        return array(
            'name' => '',
            'description' => '',
            'mixins' => array(),
            'dependencies' => array(),
            'compression' => false,
            'attachable_files' => '',
            'label' => '',
            'ignore' => array(),
        );
    }

    protected function setFormatValues(){
        return array(
            'name' => array(
                'type' => 'string',
            ),
            'description' => array(
                'type' => 'string',
            ),
            'mixins' => array(
                'type' => 'array',
                'prepare' => function($value){
                    foreach ($value as $v){
                        $check = true;
                        if (empty($v['mixin']) or (empty($v['tag']) and empty($v['id']) and empty($v['class']))) $check = false;
                        if (!empty($v['tag']) and gettype($v['tag']) != 'string') $check = false;
                        if (!empty($v['id']) and gettype($v['id']) != 'string') $check = false;
                        if (!empty($v['class']) and gettype($v['class']) != 'string') $check = false;
                        if (!empty($v['mixin']) and gettype($v['mixin']) != 'string') $check = false;
                        if (!$check) throw new Exception('Incorrect mixin stack format in block configuration');
                    }
                    return $value;
                },
            ),
            'dependencies' => array(
                'type' => 'array',
                'prepare' => function($value){
                    foreach ($value as $k => $v) {
                        if (!is_string($v) or !preg_match('/^\s*[a-z-0-9-]+(?:_[a-z-0-9-]+)?\s*$/is',$v)) {
                            throw new Exception('Incorrect dependencies stack format in block configuration');
                        }
                        $value[$k] = trim($v);
                    }
                    return $value;
                },
            ),
            'compression' => array(
                'type' => 'boolean',
            ),
            'attachable_files'=> array(
                'type' => 'string',
            ),
            'label' => array(
                'type' => 'string',
                'prepare' => function($value){
                    if (!is_string($value) or !preg_match('/^\s*[a-z-0-9-]+\s*$/is',$value)) {
                        throw new Exception('Incorrect label "'.$value.'" for the block');
                    }
                    return trim($value).'_';
                }
            ),
            'ignore' => array(
                'type' => 'array',
                'prepare' => function($value){
                    foreach ($value as $k => $v) {
                        if (!is_string($v)) {
                            throw new Exception('Incorrect ignore stack format in block configuration');
                        }
                        $value[$k] = trim($v);
                    }
                    return $value;
                },
            )
        );
    }
}

/**
 * Объект блока
 *
 * Class Block
 * @property DataBlockConfig $config Конфигурации блока
 *
 * @property string $path — Путь до директории блока
 * @property string $name — Имя блока
 *
 * @property string $workspace — Имя текущей рабочей директории внутри блока
 * @property string $workspace_path — Путь до текущей рабочей директории внутри блока
 * @property string $template — Шаблон (доступно после компиляции)
 * @property string $template_path — Директория в которой лежит файл шаблона
 * @property string $template_file — Путь до шаблона
 * @property array $css — Массив CSS файлов задействованных в блоке с учетом рабочей директории
 * @property array $js — Массив JS файлов задействованных в блоке с учетом рабочей директории
 * @property array $files — Массив дополнительных файлов задействованных в блоке с учетом рабочей директории
 *
 * @property array $dependencies Используемые при компиляции зависимости (доступно после компиляции)
 * @property array $used_mixins Используемые при компиляции миксины (доступно после компиляции)
 *
 * @property boolean $is_compiled Метка true — блок скомпилирован, false — блок не скомпилирован (доступно после компиляции)
 * @property boolean $is_mixed Метка true — к блоку применены миксины, false — к блоку миксины не применены
 * @property boolean $is_merged Метка true — блок был слит с другим блоком, false — блок не подвергался слиянию
 *
 *
 * @package SimBuilder
 */
class Block {
    public $config;

    protected $path;
    protected $name;

    protected $workspace;
    protected $workspace_path;
    protected $template;
    protected $template_path;
    protected $template_file;
    protected $css = array();
    protected $css_dependencies = array();
    protected $js = array();
    protected $js_dependencies = array();
    protected $files = array();

    protected $dependencies = array();
    protected $used_mixins = array();

    protected $is_compiled = false;
    protected $is_mixed = false;
    protected $is_merged = false;

    function __isset($name){
        if (in_array($name, array('template','dependencies','used_mixins','is_mixed'))){
            if ($this->is_compiled === false) Dialog::error('The "'.$name.'" property is not available until the block is compiled');
            return !empty($this->{$name});
        }
        return isset($this->{$name});
    }

    function __get($name){
        if (in_array($name, array('template','dependencies','used_mixins','is_mixed'))){
            if ($this->is_compiled === false) Dialog::error('The "'.$name.'" property is not available until the block is compiled');
            return $this->{$name};
        }
        if (!property_exists($this, $name)) Dialog::error('The "'.$name.'" property  does not exist');

        return $this->{$name};
    }


    /**
     * Block constructor.
     * @param string $path — Путь к директории блока
     * @param array $template — Шаблон блока (необязательный)
     * @param array $config — Дополнительные конфигурации блока (необязательный)
     */
    function __construct($path, array $template = array(), array $config = array()){
        try {

            //Получаем директорию блока
            $this->path = File::realpath($path);

            //Получаем имя блока
            $block_name = explode('/', $this->path);
            $this->name = $block_name[count($block_name) - 1];

            //Определение файла конфигурации
            $getConfigFile = function($path, $name){
                if (File::exists($path . '/' . $name . '.json')) return $path . '/' . $name . '.json';
                if (File::exists($path . '/config.json')) return $path . '/config.json';
                return false;
            };

            //Получение конфигураций блока
            $config_block = array();
            if ($config_filename = $getConfigFile($this->path, $this->name)){
                if (($config_block = json_decode(File::get_content($config_filename), true)) === null) {
                    $config_block = array();
                }
            } else {
                File::create($this->path.'/config.json', '{}');
            }

            //Устанавливаем базовых конфигураций конфигурации в объект
            $this->config = new DataBlockConfig($config_block);
            $this->config->export($config); //Експорт дополнительных настроек
            $this->config->bindToFile($config_filename); //Ассоциация объекта конфигурации с файлом

            //Определение файла шаблона
            $getTemplateFile = function ($path, $name){
                if (File::exists($path . '/' . $name . '.html')) return $path . '/' . $name . '.html';
                if (File::exists($path . '/template.html')) return $path . '/template.html';
                if (File::exists($path . '/' . $name . '.tpl')) return $path . '/' . $name . '.tpl';
                if (File::exists($path . '/template.tpl')) return $path . '/template.tpl';
                return false;
            };

            //Определение CSS файла
            $getCSSFile = function($path, $name){
                if (File::exists($path . '/' . $name . '.css')) return $path . '/' . $name . '.css';
                if (File::exists($path . '/style.css')) return $path . '/style.css';
                return false;
            };

            //Определение JS файла
            $getJSFile = function($path, $name){
                if (File::exists($path . '/' . $name . '.js')) return $path . '/' . $name . '.js';
                if (File::exists($path . '/script.js')) return $path . '/script.js';
                return false;
            };

            //Определение сопутствующих файлов
            $getAllFiles = function($path, $name, DataBlockConfig $config){
                $result = array();
                //Получение массива файлов
                $files = File::find($path, false, false, array('filename','path'));
                //Формирование исключений
                $exceptions = array(preg_quote($name).'\.(?:tpl|html|css|js|json)', 'template\.(?:tpl|html)', 'style\.css', 'script\.js');
                $exceptions = array_merge($exceptions, $config->get('ignore'));
                $exceptions = '/('.implode('$)|(',$exceptions).'$)/is';
                //Фильтрация по исключениям
                foreach ($files as $item){
                    if (preg_match($exceptions, $item['path'].'/'.$item['filename'])) continue;
                    $result[$item['filename']] = $item['path'].'/'.$item['filename'];
                }
                return $result;
            };

            //Получение массива конфигураций блока
            $getBlockConfig = function($path, $name){
                if (File::exists($path . '/config.json')) {
                    $fileName = $path . '/config.json';
                } else if (File::exists($path . '/' . $name . '.json')){
                    $fileName = $path . '/' . $name . '.json';
                } else {
                    return array();
                }
                if (($fileContent = json_decode(File::get_content($fileName), true)) === null) {
                    return array();
                }
                return $fileContent;
            };

            //Значения по умолчанию
            $this->workspace = '';
            $this->workspace_path = $this->path;
            $this->template_path = '';
            $this->template_file = '';

            //Получение HTML файла основного блока
            if ($template_file = $getTemplateFile($this->workspace_path, $this->name)) {
                $this->template_path = $this->workspace_path;
                $this->template_file = $template_file;
            }

            //Получение CSS файла основного блока
            if ($cssFile = $getCSSFile($this->workspace_path, $this->name)) $this->css[] = $cssFile;

            //Получение JS файла основного блока
            if ($jsFile = $getJSFile($this->workspace_path, $this->name)) $this->js[] = $jsFile;

            //Получение массива сопутствующих файлов основного блока
            $this->files = $getAllFiles($this->workspace_path, $this->name, $this->config);

            //Получение системных файлов шаблона (если он установлен)
            if (!empty($template)){
                foreach ($template as $view){
                    File::exists(($this->workspace_path = $this->workspace_path.'/'.$view), true);

                    //Получение HTML файла шаблона
                    if ($templateFile = $getTemplateFile($this->workspace_path, $this->name)){
                        $this->template_path = $this->workspace_path;
                        $this->template_file = $templateFile;
                    }

                    //Получение CSS файла шаблона
                    if ($cssFile = $getCSSFile($this->workspace_path, $this->name)) $this->css[] = $cssFile;

                    //Получение JS файла шаблона
                    if ($jsFile = $getJSFile($this->workspace_path, $this->name)) $this->js[] = $jsFile;

                    //Получение персональных конфигураций шаблона
                    $blockConfig = $getBlockConfig($this->workspace_path, $this->name);
                    if (!empty($blockConfig)) $this->config->merge($blockConfig);

                    //Получение массива сопутствующих файлов шаблона
                    $allFiles = $getAllFiles($this->workspace_path, $this->name, $this->config);
                    if (!empty($allFiles)) $this->files = array_merge($this->files, $allFiles);
                }

                $this->workspace = implode('_', $template);
            }

        } catch (Exception $error){
            $error->getError('Initializing a block'.((isset($this->name)) ? ' "'.$this->name.'"' : ''));
        }
    }

    public function compile(array $mixins = array()){
        if ($this->is_compiled !== false) Dialog::error('Block "'.$this->name.'" is already compiled');

        try {

            $this->template = '';
            $this->dependencies = $this->config->get('dependencies');

            //Если у блока есть HTML шаблон
            if (!empty($this->template_file)) {

                //Получаем HTML шаблон
                $this->template = File::get_content($this->template_file);

                //Выполняем объединение списков объявленных зависимостей в шаблоне и в конфигурациях блока
                if (preg_match_all('/#([a-z-0-9-]+(?:_[a-z-0-9-]+)*)#/is', $this->template, $dependencies)) {
                    $dependencies = $dependencies[1];
                    foreach ($dependencies as $v) {
                        if (!in_array($v, $this->dependencies)) $this->dependencies[] = $v;
                    }
                }

                //Компилируем в шаблон блока переданные миксины
                $this->used_mixins = $this->compileMixins($mixins);

                //Ставим отметку что миксины для блока скомпилированы
                $this->is_mixed = (empty($this->used_mixins)) ? false : true;
            }

            //Помечаем объект как скомпилированный
            $this->is_compiled = true;

        } catch (Exception $error){
            $error->getError('Compilation of block "'.$this->name.'"'.((!empty($this->workspace)) ? ' with template "'.$this->workspace.'"' : ''));
        }

        return $this;
    }

    public function merge(Block $block){

        if ($this->is_compiled === false) {
            Dialog::error('Merging of blocks ('.$this->name.' - '.$block->name.') is impossible. Parent block "'.$this->name.'" is not compiled');
        }
        if ($block->is_compiled === false) {
            Dialog::error('Merging of blocks ('.$this->name.' - '.$block->name.') is impossible. Children block "'.$block->name.'" is not compiled');
        }

        //Обработка ситуации с рекурсивным подключением блоков
        if ($this->workspace_path == $block->workspace_path) {
            Dialog::error('Recursive connection of blocks in "'.$this->workspace_path.'"');
        }

        //Вставляем шаблон зависимого блока в родительский по ключу
        $block_template_key = (empty($block->workspace)) ? $block->name : $block->name.'_'.$block->workspace;
        $block_template = (!empty($block->template)) ? '<!--block:'.$block->config->get('label').$block_template_key.'-->'.$block->template.'<!--end:'.$block->config->get('label').$block_template_key.'-->' : '';

        if ($this->replaceByDependencyKey($block_template_key,$block_template)){
            $this->used_mixins = Procedure::mergeMixins($this->used_mixins, $block->used_mixins);
        }

        //Объедняем CSS файлы родительского блока и зависимого
        $block_css = array_merge($block->css_dependencies, $block->css);
        $this_css = array_merge($this->css_dependencies, $this->css);
        foreach ($block_css as $item){
            if (in_array($item, $this_css)) continue;
            $this->css_dependencies[] = $item;
        }

        //Объединяем JS файлы родительского блока и зависимого
        $block_js = array_merge($block->js_dependencies, $block->js);
        $this_js = array_merge($this->js_dependencies, $this->js);
        foreach ($block_js as $item){
            if (in_array($item, $this_js)) continue;
            $this->js_dependencies[] = $item;
        }

        //Объединяем сопутствующие файлы родительского блока и зависимого
        foreach ($block->files as $filename => $filepath){
            if (in_array($filepath, $this->files)) continue;
            $this->files[$filename] = $filepath;
        }

        //Объединяем списки зависимостей родительского блока и зависимого
        foreach ($block->dependencies as $item){
            if (in_array($item, $this->dependencies)) continue;
            $this->dependencies[] = $item;
        }

        //Устанавливаем метку о том что блок был слит
        $this->is_merged = true;

        return $this;
    }

    public function replaceByDependencyKey($key, $value){
        if ($this->is_compiled === false) Dialog::error('Replacement in the "'.$this->name.'" block is not available until it is compiled');

        if (empty($key)) return false;
        if (!is_string($value) and !is_array($value)) return false;

        if (is_string($key)){
            $key = '#'.trim($key).'#';
        } elseif (is_array($key)){
            foreach ($key as $k => $v){
                if (!is_string($v)) return false;
                $key[$k] = '#'.trim($v).'#';
            }
        }

        $this->template = str_replace($key, $value, $this->template, $count);
        if ($count>0){
            return true;
        } else {
            return false;
        }
    }

    public function save($path){
        if ($this->is_compiled === false) Dialog::error('Saving the compiled files of the "'.$this->name.'" block is not available until compiled');

        //Формируем имя директории для сохранения скомпилированных файлов блока
        $block_dir = (empty($this->workspace)) ? $this->config->get('label').$this->name : $this->config->get('label').$this->name.'_'.$this->workspace;
        $block_path = $path.'/'.$block_dir;

        if (File::exists($block_path)){
            //Удаляем из директории компиляции неактуальные файлы
            $control_stack = array_merge(
                array_keys($this->files),
                array($this->name.'.html',$this->name.'.css',$this->name.'.js')
            );
            foreach (File::find($block_path, false, false, array('filename','path')) as $cml_block_file){
                if (in_array($cml_block_file['filename'],$control_stack)) continue;
                unlink($cml_block_file['path'].'/'.$cml_block_file['filename']);
            }
        } else {
            //Создаем директорию для сохранения файлов шаблона
            Dir::create($block_path);
        }

        //Получаем основные составляющие блока
        $template = (!empty($this->template)) ? '<!--block:' . $block_dir . '-->' . $this->template . '<!--end:' . $block_dir . '-->' : '';
        $css = $this->compileCSS();
        $js = $this->compileJS();


        //Выполняем перенос закрепленных за шаблоном файлов с заменой соответствующих
        //ссылок в HTML-коде шаблона
        if (preg_match_all('/\/?_attach\/([\w-\.\/]+)/is', $template, $attach_matches)){
            $attach_dir = $this->config->get('attachable_files');
            foreach($attach_matches[0] as $k => $attach_link){

                $attach_uri = $attach_matches[1][$k];
                $attach_alias = str_replace('/','_',$attach_uri);
                $attach_source_file = $attach_dir.'/'.$attach_uri;

                $template = Procedure::strReplaceOnce($attach_link, $attach_alias, $template);

                if (!File::exists($attach_source_file)){
                    Dialog::warning('File "'.$attach_uri.'" not found in attach directory');
                    continue;
                }

                if (@!copy($attach_source_file, $block_path.'/'.$attach_alias)) {
                    Dialog::warning('Could not copy file "'.$attach_source_file.'" to directory "'.$block_path.'"');
                    continue;
                }
            }
        };

        //Сохраняем основные составляющие блока
        try {
            File::create($block_path.'/'.$this->name.'.html', $template);
            File::create($block_path.'/'.$this->name.'.css', $css);
            File::create($block_path.'/'.$this->name.'.js', $js);
        } catch (Exception $error){
            $error->getError('Save the compiled files of the "'.$this->name.'" block');
        }

        //Выполняем перенос сопутствующих файлов блока
        foreach ($this->files as $filename => $filepath){
            if (!copy($filepath, $block_path.'/'.$filename)) {
                Dialog::warning('Could not copy file "'.$filename.'" to directory "'.$block_path.'"');
            }
        }

        return $block_path;
    }

    public function getStamp(){
        try{
            return Procedure::getBlockStamp($this->path, $this->workspace_path);
        } catch (Exception $error){
            $error->getError('Getting a stamp for the block');
            die();
        }
    }

    protected function compileCSS(){
        if (empty($this->css) and empty($this->css_dependencies)) return '';
        $css = '';
        $css_files = array_merge($this->css_dependencies,$this->css);

        if ($this->config->get('compression') === false) {
            //Выполняем объединение файлов
            foreach ($css_files as $item) {
                $css .= File::get_content($item);
                $css .= "\n\r";
            }
        } else {
            //Выполняем парсинг и объединение CSS файлов
            $css_index = array();
            foreach ($css_files as $item) {
                if (($_css_index = $this->parseCSS(File::get_content($item))) !== false) {
                    if (empty($css_index)) {
                        $css_index = $_css_index;
                    } else {
                        $css_index = $this->mergeIndexCSS($css_index, $_css_index);
                    }
                }
            }
            $css = $this->buildCSS($css_index);
        }

        return $css;
    }

    protected function buildCSS(array $css_index){
        if (empty($css_index) or !is_array($css_index)) return '';
        $css = '';
        foreach ($css_index as $s_key => $s_value){

            if (is_string($s_value)){
                if ($s_key == '@charset'){
                    $css.= $s_key.' '.$s_value.';';
                } else {
                    $css.= $s_key.':'.$s_value.';';
                }
                continue;
            }

            if (is_array($s_value)){
                if (key_exists(0, $s_value)){
                    foreach ($s_value as $p_value){
                        $css.= $s_key.':'.$p_value.';';
                    }
                } elseif(in_array($s_key, array('top', 'main', 'bottom'))) {
                    $css.= $this->buildCSS($s_value);
                } else {
                    if ($s_key_ = strstr($s_key, '#', true)){
                        $s_key = $s_key_;
                    }
                    $css.= $s_key.'{';
                    $css.= $this->buildCSS($s_value);
                    $css.='}';
                }
                continue;
            }

            if ($s_value === NULL){
                $css.= $s_key.';';
                continue;
            }
        }

        return $css;
    }

    protected function mergeIndexCSS(array $css_index_1, array $css_index_2){
        if (empty($css_index_1) and empty($css_index_2)) return array();
        if (empty($css_index_1)) return $css_index_2;
        if (empty($css_index_2)) return $css_index_1;

        foreach ($css_index_2 as $k => $v) {
            if (key_exists($k, $css_index_1)){

                if (is_array($css_index_1[$k]) and is_array($v)){
                    if (key_exists(0, $css_index_1[$k]) and key_exists(0, $v)){
                        $css_index_1[$k] = array_merge($css_index_1[$k],array_diff($v, $css_index_1[$k]));
                    } else {
                        $css_index_1[$k] = $this->mergeIndexCSS($css_index_1[$k], $v);
                    }
                } else {
                    $css_index_1[$k] = $v;
                }

            } else {
                $css_index_1[$k] = $v;
            }
        }

        return $css_index_1;
    }

    protected function parseCSS($css_source, $method = 's'){
        if (empty($css_source) or !is_string($css_source)) return false;

        //Устанавливаем основной паттерн поиска
        switch ($method){
            case 's':
                //Патерн для поиска селекторов
                $pattern = '/(?:(?(R)\{|(.*?)\{)((?:[^\{\}]+|(?R))*)(?(R)\}|\}))/isx';
                break;
            case 'p':
                //Патерн для поиска свойств селекторов
                $pattern =  '/([\w\s-]+)\:(.*?)(?:;|$)/is';
                break;
            default:
                return false;
                break;
        }

        //Устанавливаем накопительные переменные
        $css = array(
            'top'=>array(),
            'main'=>array(),
            'bottom'=>array()
        );

        //Удаляем комментарии из контекста CSS
        $css_source = preg_replace('/\/\*.*?\*\//is','',$css_source);

        //Парсинг CSS
        if (preg_match_all($pattern, $css_source, $css_match)){
            foreach ($css_match[1] as $index=>$key){

                //$css_match[1] — имена селеторов (метод s) | имена свойств (метод p)
                //$css_match[2] — содержание селекторов (метод s) | значения свойств (метод p)

                //Удалаем из исходника найденный блок для предотвращения каллизий
                $css_source = str_replace($css_match[0][$index], '', $css_source);

                if ($method == 's'){

                    //Регулярное выражение в дополнении к имени селектора может вернуть один или более
                    //однострочных селекторов, расположенных сразу над искомым сразу над искомым.

                    //Обрабатываем возможные строчные селекторы
                    if (preg_match_all('/(@import|@charset)(.*?);/is', $key, $key_match)){
                        foreach ($key_match[0] as $k => $v){
                            $key = str_replace($v, '', $key);
                            if (strtolower($key_match[1][$k]) == '@import') $css['main'][$key_match[1][$k].$key_match[2][$k]] = NULL;
                            if (strtolower($key_match[1][$k]) == '@charset') $css['top'][$key_match[1][$k]] = trim($key_match[2][$k]);
                        }
                    }

                    //Селекторые могут иметь вложенные селекторы (например конструкция @media)

                    //Выполняем проверку наличия вложенных селекторов, при их отсутствии, парсим вложенность
                    //методом p (поиск свойств и их значений в селекторе)
                    if (($item = $this->parseCSS($css_match[2][$index], 's')) === false){
                        $item = $this->parseCSS($css_match[2][$index],'p');
                    }

                    //Обрабатываем результат выполнения рекурсивного вызова текущего метода
                    if ($item !== false){
                        $item = array_merge($item['top'], $item['main'], $item['bottom']);
                    }

                } else {
                    $item = $css_match[2][$index];
                }


                if ($item !== false){
                    $key = trim($key);

                    //Предобработка текстового значения
                    if (is_string($item)){
                        $item = preg_replace('/\s{1,}/is',' ',trim($item));
                    }

                    //Обработка повторяющихся свойств с разными значениями.
                    //Например:
                    // display: -webkit-flex;
                    // display: flex;
                    if (is_string($item) and key_exists($key, $css['main'])) {
                        if (is_array($css['main'][$key])){
                            array_push($css['main'][$key], $item);
                        } elseif (is_string($css['main'][$key])) {
                            $css['main'][$key] = array($css['main'][$key],$item);
                        }
                        continue;
                    }

                    //Обработка @font-face
                    if (is_array($item) and stristr($key,'@font-face') !== false){
                        //print_r($item);
                        $key = $key.'#'.sha1(serialize($item));
                        foreach ($item as $k=>$v){
                            $css['top'][$key][$k] = $v;
                        }
                        continue;
                    }

                    //Обработака других @-правил
                    if (is_array($item) and stristr($key,'@') !== false){
                        foreach ($item as $k=>$v){
                            $css['bottom'][$key][$k] = $v;
                        }
                        continue;
                    }

                    //Базовая обработка
                    $css['main'][$key] = $item;
                }
            }

        }

        //Патерны регулярных выражений не смогут выявить однострочные селекторы, если они есдинственные в
        //файле или расположены ниже всех остальных селекторов.

        //Поиск и обработка невыявленных однострочных селекторов
        if(preg_match_all('/(@import|@charset)(.*?);/is', $css_source, $css_match)) {
            foreach ($css_match[0] as $k => $v){
                if (strtolower($css_match[1][$k]) == '@import') $css['main'][$css_match[1][$k].$css_match[2][$k]] = NULL;
                if (strtolower($css_match[1][$k]) == '@charset') $css['top'][$css_match[1][$k]] = trim($css_match[2][$k]);
            }
        }

        if (empty($css['main']) and empty($css['top']) and empty($css['bottom'])){
            return false;
        } else {
            return $css;
        }
    }

    protected function compileJS(){
        if (empty($this->js) and empty($this->js_dependencies)) return '';
        $js = '';
        $js_files = array_merge($this->js_dependencies, $this->js);

        if ($this->config->get('compression') === false) {
            //Выполняем объединение файлов
            foreach ($js_files as $item) {
                $js .= File::get_content($item);
                $js .= "\n\r";
            }
        } else {
            //Выполняем компрессию и объединения файлов
            foreach ($js_files as $item) {
                try {
                    $js .= $this->compressionJS(File::get_content($item));
                } catch (Exception $error){
                    $error->getError('Compressing javascript file — "'.$item.'"');
                }
            }
        }

        return $js;
    }

    protected function compressionJS($js_source){
        if (empty($js_source) or !is_string($js_source)) return '';

        //Зачищаем все многострочные комментарии
        $js_source = preg_replace('/\/\*.*?\*\//is','',$js_source);

        //Индексируем и заменяем подстроки находящиеся в кавычках
        $index = array();
        if (preg_match_all('/(?<!\\\\)([\\\'\\"]{1})((?!(?<!\\\\)\1).)*(?<!\\\\)\1/is', $js_source, $index_match)){
            $index = $index_match[0];
            foreach ($index as $k=>$v){
                $js_source = Procedure::strReplaceOnce($v, 'sb#'.$k.'#', $js_source);
            }
            unset($k,$v);
        }

        //Удаляем однострочные комментарии
        $js_source = preg_replace('/\/\/.*?[\n\r]/is','',$js_source);

        //Проверяем, что в контексте не осталось кавычек
        if (!empty($index)){
            if (preg_match('/.*[\\\'\\"].*/', $js_source, $quotes_match)){
                $quotes_match = $quotes_match[0];
                foreach ($index as $k => $v){
                    $v = preg_replace('/\s{1,}/is',' ', $v);
                    $quotes_match = str_replace('sb#'.$k.'#', $v, $quotes_match);
                }
                unset($k,$v);
                throw new Exception('There are unmatched quotes in line — "'.trim($quotes_match).'"');
            }
        }

        //Удалаяем все все пробельные символы
        $js_source = preg_replace('/\s{1,}/is','', $js_source);

        //Востанавливаем из индекса подстроки находящиеся в кавычках
        if (!empty($index)){
            foreach ($index as $k => $v){
                $v = preg_replace('/\s{1,}/is',' ', $v);
                $js_source = str_replace('sb#'.$k.'#', $v, $js_source);
            }
            unset($k,$v);
        }

        return $js_source;
    }

    protected function compileMixins(array $mixins = array()){

        $used_mixins = array();

        if (empty($this->template) or empty($mixins)) {
            return $used_mixins;
        }

        foreach ($mixins as $mix_item) {
            if (empty($mix_item)) continue;
            if (empty($mix_item['mixin'])) continue;

            $tag = (empty($mix_item['tag'])) ? '[a-z0-9]+' : $mix_item['tag'];
            $pattern = '';

            if (!empty($mix_item['id']) and !empty($mix_item['class'])) {
                $pattern = '/<\s*' . $tag . '\b[^\>]*\b(?:(?:(class\s*=\s*[\"\\\'](?:\s*|[\w\s-]+\s+)' . $mix_item['class'] . '(?:\s+[\w\s-]+|\s*)[\"\\\'])[^\>]*id\s*=\s*[\"\\\']\s*' . $mix_item['id'] . '\s*[\"\\\'])|(?:id\s*=\s*[\"\\\']\s*' . $mix_item['id'] . '\s*[\"\\\'][^\>]*(class\s*=\s*[\"\\\'](?:\s*|[\w\s-]+\s+)' . $mix_item['class'] . '(?:\s+[\w\s-]+|\s*)[\"\\\'])))[^\>]*>/';
            } elseif (!empty($mix_item['class'])) {
                $pattern = '/<\s*' . $tag . '\b[^\>]*\b(class\s*=\s*[\"\\\'](?:\s*|[\w\s-]+\s+)' . $mix_item['class'] . '(?:\s+[\w\s-]+|\s*)[\"\\\'])[^\>]*>/';
            }

            if (!empty($pattern)){
                if (preg_match_all($pattern, $this->template, $dom_elements)) {
                    foreach ((empty($dom_elements[2])) ? $dom_elements[1] : array_merge($dom_elements[1],$dom_elements[2]) as $k=>$v){
                        if (empty($v)) continue;
                        if (preg_match('/class\s*=\s*[\"\\\'](?:(?:\s*|[\w\s-]+\s+)' . $mix_item['mixin'] . '(?:\s+[\w\s-]+|\s*))[\"\\\']/',$v)){
                            continue; //Пропускаем если элемент содержит указанный миксин
                        }
                        $count = 0;
                        $this->template = str_replace(
                            $dom_elements[0][$k],
                            str_replace(
                                $v,
                                substr($v, 0, -1).' '.$mix_item['mixin'].substr($v, -1),
                                $dom_elements[0][$k]
                            ),
                            $this->template,
                            $count
                        );
                        if ($count>0) $used_mixins[] = $mix_item;
                    }
                }
                continue;
            }

            if (!empty($mix_item['id'])){
                $pattern = '/<\s*' . $tag . '\b[^\>]*\bid\s*=\s*[\"\\\']\s*' . $mix_item['id'] . '\s*[\"\\\'][^\>]*>/';
            } elseif (!empty($mix_item['tag'])){
                $pattern = '/<\s*' . $tag . '\b[^\>]*>/';
            }

            if (!empty($pattern)){
                if (preg_match_all($pattern, $this->template, $dom_elements)) {
                    foreach ($dom_elements[0] as $v){
                        if (empty($v)) continue;
                        $count = 0;
                        if (preg_match('/class\s*=\s*[\"\\\'](?:((?:\s*|[\w\s -]+\s+)' . $mix_item['mixin'] . '(?:\s+[\w\s-]+|\s*))|([\w\s-]+))[\"\\\']/', $v, $class)){
                            if (empty($class[2])) continue; //Пропускаем если элемент содержит указанный миксин
                            $class = $class[0];
                            $this->template = str_replace($v, str_replace($class, substr($class, 0, -1).' '.$mix_item['mixin'].substr($class, -1), $v), $this->template, $count);
                        } else {
                            $this->template = str_replace($v, preg_replace('/(<[^>]+)(>)/','${1} class="'.$mix_item['mixin'].'"${2}',$v), $this->template, $count);
                        }
                        if ($count>0) $used_mixins[] = $mix_item;
                    }
                }
                continue;
            }

        }

        return $used_mixins;
    }

}

/**
 * Объект данных
 *
 * Class data
 * @package SimBuilder
 */
abstract class Data {

    /**
     * @var string Путь к файлу для сохранения данных
     */
    protected $file;

    /**
     * @var array Массив со всеми значениями объекта
     */
    protected $data = array();

    /**
     * @var array Нотация для валидации и предобработки значений
     */
    protected $format = array();

    /**
     * data constructor.
     * @param array $data
     */
    final function __construct(array $data = array()){
        $this->data = $this->setDefaultValues();
        $this->format = $this->setFormatValues();
        $this->export($data);
        $this->check();
    }

    /**
     * Используется для установки значений по умолчанию в конструкторе объекта
     *
     * @return array
     */
    abstract protected function setDefaultValues();

    /**
     * Используется для установки формата данных в конструкторе объекта
     *
     * @return array
     */
    abstract protected function setFormatValues();

    /**
     * Выполняет привязку объекта данных к файлу
     *
     * @param $path
     * @return $this
     */
    public function bindToFile($path){
        $this->file = File::realpath($path);
        return $this;
    }

    /**
     * Выполняет сохранение данных в привязанный файл в формате json
     *
     * @return $this
     * @throws Exception
     */
    public function save(){
        if (empty($this->file)) throw new Exception('No binded file to save');
        File::put_content($this->file, $this->data);
        return $this;
    }

    /**
     * Массовая установка значений через ассоциативный массив. Ключи элементов массива
     * интерпритируются как идентификаторы значений объекта
     *
     * @param array $data
     * @return $this
     */
    public function export(array $data = array()){
        foreach ($data as $name => $value) {
            $data[$name] = $this->validation($name, $value);
        }
        $this->data = array_merge($this->data, $data);
        return $this;
    }

    /**
     * Объединение значений через ассоциативный массив. Ключи элементов массива
     * интерпритируются как идентификаторы значений объекта
     *
     * @param array $data
     * @return $this
     */
    public function merge(array $data = array()){
        foreach ($data as $name => $value) {
            if ($this->data[$name] and is_array($this->data[$name])){
                $value = array_unique(array_merge($this->data[$name], $value), SORT_REGULAR);
            }
            $this->set($name, $value);
        }
        return $this;
    }

    /**
     * Устанавливает значение за указанным идентификатором
     *
     * @param string $name Идентификатор значения
     * @param mixed $value Значение
     * @return $this
     */
    public function set($name, $value){
        $this->data[$name] = $this->validation($name, $value);
        return $this;
    }

    public function add($name, $value){
        $value = $this->validation($name, $value);
        if (gettype($this->data[$name]) == 'array'){
            $this->data[$name] = array_merge($this->data[$name], $value);
        } else {
            $this->data[$name] = $value;
        }
        return $this;
    }

    /**
     * Возвращает значкение по его идентмификатору
     *
     * @param string $name Идентификатор значения
     * @return mixed
     * @throws Exception
     */
    public function get($name){

        if (!key_exists($name, $this->data)) {
            throw new Exception('Property "'.$name.'" not found');
        }

        return $this->data[$name];
    }

    /**
     * Возвращает массив всех установленных значений объекта
     *
     * @return array
     */
    public function getArray(){
        return $this->data;
    }

    /**
     * Возвращает JSON массив всех устанаовленных значений объекта
     *
     * @return string
     */
    public function getJSON(){
        return json_encode($this->getArray(), true);
    }

    /**
     * Проверяет значение на пустоту
     *
     * @param string $name Идентификатор значения
     * @return bool
     */
    public function isEmpty($name){
        return empty($this->data[$name]);
    }

    /**
     * Выаолняет валидацию и обработку значения с учетом нотации format
     *
     * @param string $name Идентификатор значения
     * @param mixed $value Знаячение
     * @return mixed Обработанное значение
     * @throws Exception
     */
    protected function validation($name, $value){

        //Проверяем может ли быть текущее значение записано в объект
        if (empty($this->format[$name])) {
            throw new Exception('Property "'.$name.'" not found');
        }

        //Проверяем тип переменной
        if (!empty($this->format[$name]['type'])) {
            if (gettype($value) != $this->format[$name]['type']) {
                throw new Exception('Invalid data type for property "'.$name.'" (expected ' . $this->format[$name]['type'] . ')');
            }
        }

        //Проверяем обязательность заполнения поля (чтобы его не могли назначить пустым)
        if (isset($this->format[$name]['required']) and empty($value)) {
            throw new Exception('Property "'.$name.'" must be set');
        }

        //Выполняем пользовательский обработчик
        if (!empty($value) and !empty($this->format[$name]['prepare']) and gettype($this->format[$name]['prepare']) == 'object') {
            $value = $this->format[$name]['prepare']($value);
        }

        return $value;
    }

    /**
     * Проверяет заполненность обязательных значений указанных в нотации format
     *
     * @param bool $error
     * @return array|bool
     * @throws Exception
     */
    public function check($error = false){
        $e = array();
        foreach ($this->format as $k => $item) {
            if (isset($item['required']) and $item['required'] === true) {
                if (empty($this->data[$k])) {
                    $e[] = $k;
                }
            }
        }

        if (!empty($e)) {
            if ($error === false) {
                return $e;
            } else {
                throw new Exception('Properties "'.implode('", "', $e).'" must be set');
            }
        }

        return true;
    }
}

/**
 * Класс для управления файлами
 *
 * Class file
 * @package SimBuilder
 */
class File {

    /**
     * @param $path
     * @return string
     * @throws Exception
     */
    static public function realpath($path){
        $path_result = realpath($path);
        if ($path_result === false) throw new Exception('File not found — "'.$path.'"');
        return $path_result;
    }

    /**
     * @param $path
     * @param bool $error
     * @return bool
     * @throws Exception
     */
    static public function exists($path, $error = false){
        if (!file_exists($path)) {
            if ($error !== false) {
                throw new Exception(((is_string($error)) ? $error : 'File not found') . ' — "' . $path . '"');
            }
            return false;
        }
        return true;
    }

    /**
     * @param $path
     * @param $content
     * @throws Exception
     */
    static public function put_content($path, $content){
        if (!is_string($path)){
            throw new Exception('Invalid path to file for writing');
        }

        if (is_array($content)){
            $content = json_encode($content, JSON_PRETTY_PRINT | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
        } elseif (!is_string($content)){
            throw new Exception('Invalid data format for writing to file — "'.$path.'" (expected string values)');
        }

        if (file_put_contents($path, $content) === false) {
            throw new Exception('Can not write to file — "' . $path . '"');
        }
    }

    /**
     * @param $path
     * @return bool|mixed|string
     * @throws Exception
     */
    static public function get_content($path){
        $content = file_get_contents($path);
        if ($content === false || ($content === '' && is_dir($path))) {
            throw new Exception('Unable to load file "' . $path . '"');
        }
        return $content;
    }

    /**
     * @param $path
     * @param $content
     * @throws Exception
     */
    static public function create($path, $content){
        $path = pathinfo($path);
        if (!file_exists($path['dirname'])) {
            if (mkdir($path['dirname'], 0777, true) === false) {
                throw new Exception('Can not create directory — "' . $path['dirname'] . '"');
            }
        }

        if (@file_put_contents($path['dirname'] . '/' . $path['basename'], $content) === false) {
            throw new Exception('Can not create file — "' . $path['dirname'] . '/' . $path['basename'] . '"');
        }

    }

    /**
     * @param $path
     * @throws Exception
     */
    static public function remove($path){
        if (unlink($path) === false) throw new Exception('Can not delete file — "' . $path . '"');
    }

    /**
     * @param $dir
     * @param bool $recursive
     * @param bool $regex
     * @param array $options
     * @return array
     * @throws Exception
     */
    static function find($dir, $recursive = false, $regex=false, array $options = array()){
        if (empty($dir) or !is_string($dir)) return array();

        if ($recursive === false){
            $files = new \FilesystemIterator($dir);
        } else {
            $directory = new \RecursiveDirectoryIterator($dir);
            $files = new \RecursiveIteratorIterator($directory);
        }

        if (!empty($regex) and is_string($regex)){
            $files = new \RegexIterator($files, $regex);
        }

        if (empty($options)){
            $options = array('path','filename','extension','size','atime','mtime','ctime');
        }

        $arFiles = array();
        $k = 0;
        foreach ($files as $i){

            /** @var $i \SplFileInfo */

            if (!$i->isFile()) continue;

            foreach ($options as $option){
                switch ($option){
                    case 'path': $arFiles[$k]['path'] = $i->getPath(); break;
                    case 'filename': $arFiles[$k]['filename'] = $i->getFilename(); break;
                    case 'extension': $arFiles[$k]['extension'] = $i->getExtension(); break;
                    case 'size': $arFiles[$k]['size'] = $i->getSize(); break;
                    case 'atime': $arFiles[$k]['atime'] = $i->getATime(); break;
                    case 'mtime': $arFiles[$k]['mtime'] = $i->getMTime(); break;
                    case 'ctime': $arFiles[$k]['ctime'] = $i->getCTime(); break;
                    default:
                        throw new Exception('Invalid option for searching files');
                }
            }

            ++$k;
        }

        return $arFiles;
    }

    /**
     * @param $path
     * @return int
     */
    static public function time($path){
        $path = self::realpath($path);
        return filemtime($path);
    }

    /**
     * @param $path
     * @return int
     */
    static public function from_time($path){
        return time()-self::time($path);
    }

}

/**
 * Класс для управления директориями
 *
 * Class Dir
 * @package SimBuilder
 */
class Dir {

    static public function checkpath($path){
        if (!preg_match('/^\/[a-z0-9_\-\/\. ]{0,220}$/is', $path)) {
            throw new Exception('Invalid directory — "' . $path . '"');
        }
    }

    static public function realpath($path){
        $path_result = realpath($path);
        if ($path_result === false) throw new Exception('Directory not found — "'.$path.'"');
        return $path_result;
    }

    /**
     * @param $path
     * @return bool
     * @throws Exception
     */
    static public function create($path){
        Dir::checkpath($path);
        if (file_exists($path)) return true;
        if (mkdir($path, 0777, true) === false) {
            throw new Exception('Can not create directory — "' . $path . '"');
        }
        return true;
    }

    static public function stamp($path){
        Dir::checkpath($path);
        $path = Dir::realpath($path);
        $files = File::find($path, true, false, array('mtime'));
        return sha1(serialize($files));
    }

    static public function clear($path){
        Dir::checkpath($path);
        $path = Dir::realpath($path);

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach($files as $file) {
            if ($file->getFilename() === '.' || $file->getFilename() === '..') {
                continue;
            }
            if ($file->isDir()){
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }
    }

    static public function path($dir_from, $dir_to){
        Dir::checkpath($dir_from);
        Dir::checkpath($dir_to);

        $dir_from = explode('/',Dir::realpath($dir_from));
        $dir_to = explode('/',Dir::realpath($dir_to));

        $i = -1;
        $d1 = '';
        $d2 = '';

        while(1){
            ++$i;

            if (!isset($dir_from[$i]) and !isset($dir_to[$i])){
                break;
            }

            if (isset($dir_from[$i]) and isset($dir_to[$i]) and $dir_from[$i] == $dir_to[$i]){
                continue;
            } else {
                if (isset($dir_from[$i])) {
                    $d1.= '../';
                }

                if (isset($dir_to[$i])) {
                    $d2.= '/'.$dir_to[$i];
                }
            }

        }
        $result = str_replace('//', '/', $d1.$d2.'/');
        if ($result[0] == '/') $result = substr($result, 1);
        return $result;
    }

}

/**
 * Класс для реализации интерфейсов общения с пользователем через console
 *
 * Class Dialog
 * @package SimBuilder
 */
class Dialog {

    final private static function style($style = 'default'){
        $styles = array(
            'default' => "\033[0m",
            'black' => "\033[1;30m",
            'red' => "\033[1;31m",
            'green' => "\033[1;32m",
            'yellow' => "\033[1;33m",
            'blue' => "\033[1;34m",
            'purple' => "\033[1;35m",
            'cyan' => "\033[1;36m",
            'white' => "\033[1;37m"
        );
        $style = (isset($styles[$style])) ? $styles[$style] : $styles['default'];
        echo $style;
    }

    final private static function arrayToString($array, $padding = 1){
        $result = '';
        foreach ($array as $key => $value){
            $key = is_integer($key) ? ($key+1) : $key;

            if (is_array($value)){
                $result.= str_repeat(" ", $padding).$key.': '."\n";
                $result.= self::arrayToString($value, (3 + $padding));
            } else {
                if (is_bool($value)) $value = ($value) ? 'true' : 'false';
                $result.= str_repeat(" ", $padding).$key.': '.$value."\n";
            }
        }
        return $result;
    }

    final static function message($message, $style = 'default'){
        if (is_bool($message)) $message = ($message ? 'true' : 'false');
        if (is_array($message) or is_object($message)) $message = self::arrayToString((array)$message);
        self::style($style);
        fwrite(STDERR, $message."\n");
        self::style();
    }

    final static function error($message){
        self::message('Error: '.$message, 'red');
        die();
    }

    final static function notice($message){
        self::message('Notice: '.$message, 'blue');
    }

    final static function warning($message){
        self::message('Warning: '.$message, 'yellow');
    }

    final static function success($message){
        self::message($message, 'green');
    }

    final static function request($message, $required = false, callable $prepare = null){
        do {
            self::style('blue');
            fwrite(STDERR, $message.' ');
            self::style();
            $value = trim(fgets(STDIN));
            if ($prepare) $value = $prepare($value);
        } while ($required and ($value === '' or $value === null));
        return $value;
    }
}

/**
 * Class ConsoleLauncher
 * @package SimBuilder
 */
class ConsoleLauncher {

    private $handlers = array();

    final private function getOptions(){
        /**@var array $argv**/
        global $argv;
        if (php_sapi_name() !== 'cli') return array();
        if (!$argv or !is_array($argv)) return array();
        $arguments = array_slice($argv, 1);
        $result = array();
        foreach ($arguments as $argument){
            if(preg_match('/^((?:-{1,2})?\w[\w\-]*)=(.*?)$/is', $argument, $matches)){
                $result[$matches[1]]=$matches[2];
            } else {
                $result[$argument] = '';
            }
        }
        return $result;
    }

    final public function bind($pattern, callable $handler){
        if (!is_string($pattern)) return false;
        $pattern = '/^('.rtrim(ltrim(trim($pattern),'^'),'$').')$/is';
        $pattern = preg_replace('/\s+/',' ', $pattern);
        $this->handlers[$pattern] = $handler;
        return true;
    }

    final public function start(){
        if (empty($options = self::getOptions())) return false;
        $command = implode(' ', array_keys($options));
        foreach ($this->handlers as $pattern => $handler){
            if (preg_match($pattern, $command)) $handler($options);
        }
        return true;
    }
}

/**
 * Класс с общими методами
 *
 * Class Procedure
 * @package SimBuilder
 */
class Procedure {

    static public function mergeMixins(array $mix1 = array(), array $mix2 = array()){
        if (empty($mix1) and empty($mix2)) return array();
        if (empty($mix1)) return $mix2;
        if (empty($mix2)) return $mix1;

        $arResult = $mix2;
        foreach ($mix1 as $k1 => $v1){
            $v1 = implode($v1);
            $in_mix2 = false;
            foreach ($mix2 as $k2 => $v2){
                $v2 = implode($v2);
                if ($v1 == $v2) {
                    $in_mix2 = true;
                    break;
                }
            }
            if ($in_mix2 === false) {
                $arResult[] = $mix1[$k1];
            }
        }

        return $arResult;
    }

    static function getBlockStamp($block_path, $template_path = ''){
        $block_path = Dir::realpath($block_path);
        $template_path = Dir::realpath($template_path);
        $template_dir = (!empty($template_path)) ? Procedure::strReplaceOnce($block_path, '', $template_path) : '';

        if($template_dir === $template_path){
            throw new Exception('Template directory "'.$template_path.'" is not inside the directory of the block "'.$block_path.'"');
        }

        $files = File::find($block_path, false, false, array('filename','ctime','path'));

        if (!empty($template_dir)) {
            $views = explode('/', ltrim($template_dir, "/"));
            $viewPath = $block_path;
            foreach ($views as $view){
                File::exists(($viewPath = $viewPath . '/' . $view), true);
                $files = array_merge($files, File::find($viewPath, false, false, array('filename','ctime','path')));
            }
        }

        return sha1(serialize($files));
    }

    static public function strReplaceOnce($search, $replace, $text){
        return implode((string)$replace, explode((string)$search, (string)$text, 2));
    }
}


/**
 * Объект исключений
 *
 * Class Exception
 * @package SimBuilder
 */
class Exception  extends \Exception {

    final function getError($message = ''){
        if (!empty($message)) $message = $message."\n\r";
        Dialog::error($message.$this->message);
    }

    final function getNotice($message = ''){
        if (!empty($message)) $message = $message . "\n\r";
        Dialog::notice($message.$this->message);
    }

    final function getWarning($message = ''){
        if (!empty($message)) $message = $message . "\n\r";
        Dialog::warning($message.$this->message);
    }

}