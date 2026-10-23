<?php

namespace TypechoPlugin\MGSeo;

use Typecho\Db;
use Typecho\Plugin\PluginInterface;
use Typecho\Widget\Helper\Form;
use Typecho\Widget\Helper\Layout;
use Typecho\Widget\Helper\Form\Element\Text;
use Typecho\Widget\Helper\Form\Element\Textarea;
use Typecho\Widget\Helper\Form\Element\Checkbox;
use Utils\Helper;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 动态站点地图、robots.txt 与蜘蛛日志。
 *
 * @package MGSeo
 * @author 明关
 * @version 1.1.1
 * @link https://blog.mghub.top
 * @license MIT
 */
class Plugin implements PluginInterface
{
    /**
     * 爬虫 UA 识别表：显示名 => UA 关键字（小写匹配）。
     */
    private static $bots = [
        'Google'    => 'googlebot',
        'Bing'      => 'bingbot',
        'Baidu'     => 'baiduspider',
        'Sogou'     => 'sogou',
        'Yandex'    => 'yandexbot',
        'DuckDuckGo' => 'duckduckbot',
        'Bytespider' => 'bytespider',
        '360'       => '360spider',
        'Apple'     => 'applebot',
        'Shenma'    => 'yisouspider',
    ];

    /**
     * 激活插件：建表、注册路由、挂载记录钩子与面板。
     */
    public static function activate()
    {
        // 爬虫日志表（兼容 MySQL / PostgreSQL / SQLite）
        self::createLogsTable();
        // 为存量库补建 time 索引（新库已在 DDL 中创建，重复执行幂等）
        self::ensureLogsIndexes();

        Helper::addRoute('mg_sitemap', '/sitemap.xml', Sitemap::class, 'render');
        Helper::addRoute('mg_robots', '/robots.txt', Robots::class, 'render');

        // 前台每个页面初始化时记录爬虫访问
        \Typecho\Plugin::factory('Widget\Archive')->handleInit = [__CLASS__, 'logSpider'];

        Helper::addPanel(1, 'MGSeo/Logs.php', '蜘蛛日志', '搜索引擎蜘蛛记录', 'administrator');
    }

    /**
     * 禁用插件：移除路由与面板。
     * 日志表保留，重新启用时数据不丢；清空数据可在面板内操作。
     */
    public static function deactivate()
    {
        Helper::removeRoute('mg_sitemap');
        Helper::removeRoute('mg_robots');
        Helper::removePanel(1, 'MGSeo/Logs.php');
    }

    /**
     * 插件配置面板。
     *
     * @param Form $form
     */
    public static function config(Form $form)
    {
        // 顶部说明：sitemap / robots 的检查地址与提交地址
        $siteUrl = rtrim(\Typecho\Widget::widget('Widget\Options')->siteUrl, '/');
        $note = new Layout('div', ['class' => 'message notice']);
        $note->html(
            '<p><strong>检查地址（点击可直接验证）</strong></p>'
            . '<ul>'
            . '<li>站点地图：<a href="' . $siteUrl . '/sitemap.xml" target="_blank">'
            . $siteUrl . '/sitemap.xml</a></li>'
            . '<li>robots 规则：<a href="' . $siteUrl . '/robots.txt" target="_blank">'
            . $siteUrl . '/robots.txt</a></li>'
            . '</ul>'
            . '<p>向 Google Search Console / Bing Webmaster 提交站点地图时，填写相对路径 <code>/sitemap.xml</code> 即可，'
            . '内容随站点发布动态更新，无需手动维护。</p>'
        );
        $form->addItem($note);

        $blocks = new Checkbox(
            'sitemapBlocks',
            [
                'posts'      => _t('文章链接'),
                'pages'      => _t('独立页面链接'),
                'categories' => _t('分类链接'),
                'tags'       => _t('标签链接'),
            ],
            ['posts', 'pages', 'categories', 'tags'],
            _t('站点地图包含内容'),
            _t('勾选需要写入 sitemap.xml 的内容类型。')
        );
        $form->addInput($blocks->multiMode());

        $robots = new Textarea(
            'robotsRules',
            null,
            "User-agent: *\nAllow: /",
            _t('robots.txt 自定义规则'),
            _t('每行一条标准 robots 规则（例如 Disallow: /admin/）；Sitemap 行会自动追加，无需填写。')
        );
        $form->addInput($robots);
    }

    /**
     * 个人用户配置面板。
     *
     * @param Form $form
     */
    public static function personalConfig(Form $form)
    {
    }

    /**
     * 识别当前请求是否为搜索引擎爬虫，是则记录一条日志。
     */
    public static function logSpider()
    {
        try {
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $bot = self::detectBot($ua);
            if (null === $bot) {
                return;
            }

            $request = \Typecho\Request::getInstance();
            $options = \Typecho\Widget::widget('Widget\Options');

            $db = Db::get();
            $db->query($db->insert('table.mgseo_logs')->rows([
                'bot'  => $bot,
                'path' => substr((string) $request->getRequestUri(), 0, 500),
                'ip'   => $request->getIp(),
                'time' => (int) $options->time,
            ]));

            // 小概率清理 30 天前的日志，保持表轻量
            if (mt_rand(1, 100) <= 3) {
                $db->query(
                    $db->delete('table.mgseo_logs')
                        ->where('time < ?', time() - 30 * 86400)
                );
            }
        } catch (\Throwable $e) {
            // 记录失败不影响页面正常访问
        }
    }

    /**
     * 从 UA 中识别爬虫，返回显示名；非爬虫返回 null。
     *
     * @param string $ua
     * @return string|null
     */
    private static function detectBot($ua)
    {
        $ua = strtolower((string) $ua);
        if ($ua === '') {
            return null;
        }

        foreach (self::$bots as $name => $keyword) {
            if (false !== strpos($ua, $keyword)) {
                return $name;
            }
        }

        return null;
    }

    /**
     * 创建爬虫日志表，按当前数据库适配器生成对应方言（MySQL / PostgreSQL / SQLite）。
     *
     * @throws \Typecho\Plugin\Exception
     */
    private static function createLogsTable()
    {
        $db = Db::get();
        $name = $db->getPrefix() . 'mgseo_logs';
        $adapter = $db->getAdapterName();

        if (false !== strpos($adapter, 'Mysql')) {
            $sql = "CREATE TABLE IF NOT EXISTS `{$name}` (
                `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                `bot` varchar(32) DEFAULT NULL,
                `path` varchar(500) DEFAULT NULL,
                `ip` varchar(64) DEFAULT NULL,
                `time` int(10) unsigned DEFAULT '0',
                PRIMARY KEY (`id`),
                KEY `time` (`time`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        } elseif (false !== strpos($adapter, 'Pgsql')) {
            $sql = "CREATE TABLE IF NOT EXISTS \"{$name}\" (
                \"id\" SERIAL PRIMARY KEY,
                \"bot\" varchar(32) DEFAULT NULL,
                \"path\" varchar(500) DEFAULT NULL,
                \"ip\" varchar(64) DEFAULT NULL,
                \"time\" integer DEFAULT 0
            )";
        } elseif (false !== strpos($adapter, 'SQLite')) {
            $sql = "CREATE TABLE IF NOT EXISTS \"{$name}\" (
                \"id\" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
                \"bot\" varchar(32) DEFAULT NULL,
                \"path\" varchar(500) DEFAULT NULL,
                \"ip\" varchar(64) DEFAULT NULL,
                \"time\" integer DEFAULT 0
            )";
        } else {
            throw new \Typecho\Plugin\Exception(
                _t('MGSeo 不支持的数据库类型：%s', $adapter)
            );
        }

        $db->query($sql, Db::WRITE);
    }

    /**
     * 为日志表 time 字段补建索引，供按时间清理与时间范围查询使用。
     *
     * PostgreSQL / SQLite 支持 CREATE INDEX IF NOT EXISTS；
     * MySQL 不支持该语法，重复建索引时会抛异常，捕获即可（幂等）。
     *
     * @throws \Typecho\Plugin\Exception
     */
    private static function ensureLogsIndexes()
    {
        $db = Db::get();
        $name = $db->getPrefix() . 'mgseo_logs';
        $adapter = $db->getAdapterName();

        try {
            if (false !== strpos($adapter, 'Pgsql') || false !== strpos($adapter, 'SQLite')) {
                $db->query(
                    "CREATE INDEX IF NOT EXISTS \"{$name}_time\" ON \"{$name}\" (\"time\")",
                    Db::WRITE
                );
            } elseif (false !== strpos($adapter, 'Mysql')) {
                $db->query("ALTER TABLE `{$name}` ADD INDEX `time` (`time`)", Db::WRITE);
            }
        } catch (\Throwable $e) {
            // 索引已存在（含新库 DDL 已创建的情况）时不阻断插件启用
        }
    }
}
