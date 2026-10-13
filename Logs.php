<?php

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

include 'common.php';
include 'header.php';
include 'menu.php';

$user->pass('administrator');

$db = \Typecho\Db::get();

// 清空日志
if ($request->get('do') === 'clear') {
    $security->protect();
    $db->query($db->delete('table.mgseo_logs'));
    $response->redirect($options->adminUrl('extending.php?panel=MGSeo%2FLogs.php', true));
}

// 各爬虫统计（使用查询构造器，兼容所有数据库）
$summary = $db->fetchAll(
    $db->select('bot', ['COUNT(*)' => 'cnt'], ['MAX(time)' => 'last'])
        ->from('table.mgseo_logs')
        ->group('bot')
        ->order('cnt', \Typecho\Db::SORT_DESC)
);

// 最近 100 条
$logs = $db->fetchAll(
    $db->select()->from('table.mgseo_logs')
        ->order('id', \Typecho\Db::SORT_DESC)
        ->limit(100)
);

// 日志记录的是 UTC 时间，按站点设置的时区偏移转换为本地时间
$timeOffset = (int) $options->timezone;

$clearUrl = $security->getRootUrl('extending.php?panel=MGSeo%2FLogs.php&do=clear');
?>
<div class="main">
<div class="body container">
    <div class="typecho-page-title">
        <h2>搜索引擎蜘蛛日志</h2>
    </div>
    <div class="row typecho-page-main" role="main">
        <div class="col-mb-12">

            <div class="typecho-table-wrap" style="margin-bottom:24px">
                <table class="typecho-list-table">
                    <thead>
                        <tr>
                            <th>搜索引擎</th>
                            <th>抓取次数</th>
                            <th>最近抓取</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($summary)): ?>
                        <?php foreach ($summary as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['bot'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo (int) $row['cnt']; ?></td>
                            <td><?php echo date('Y-m-d H:i:s', $row['last'] + $timeOffset); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <tr>
                            <td colspan="3"><h6 class="typecho-list-table-title">暂无蜘蛛记录，等待搜索引擎抓取后自动显示</h6></td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <p>
                <a class="btn btn-warn" lang="你确认要清空全部爬取日志吗？" href="<?php echo $clearUrl; ?>">清空日志</a>
                <span class="list-hint">（仅保留最近 100 条，超过 30 天的记录会自动清理）</span>
            </p>

            <div class="typecho-table-wrap" style="margin-top:18px">
                <table class="typecho-list-table">
                    <thead>
                        <tr>
                            <th>搜索引擎</th>
                            <th>受访路径</th>
                            <th>IP</th>
                            <th>时间</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($logs)): ?>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($log['bot'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($log['path'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($log['ip'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo date('Y-m-d H:i:s', $log['time'] + $timeOffset); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <tr>
                            <td colspan="4"><h6 class="typecho-list-table-title">当前无日志</h6></td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</div>
</div>

<?php
include 'copyright.php';
include 'common-js.php';
include 'footer.php';
