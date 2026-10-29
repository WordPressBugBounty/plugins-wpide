<?php
defined( 'ABSPATH' ) || exit;

return [
    [
        'route' => [
            'GET', '/getchangelog', '\WPIDE\App\Controllers\ChangelogController@get',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
        ],
    ],
    [
        'route' => [
            'GET', '/', '\WPIDE\App\Controllers\ViewController@index',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
        ],
    ],
    [
        'route' => [
            'GET', '/getuser', '\WPIDE\App\Controllers\AuthController@getUser',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
        ],
    ],
    [
        'route' => [
            'GET', '/getconfig', '\WPIDE\App\Controllers\ConfigController@getConfig',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
        ],
    ],
    [
        'route' => [
            'POST', '/updateconfig', '\WPIDE\App\Controllers\ConfigController@updateConfig',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
        ],
    ],
    [
        'route' => [
            'POST', '/changedir', '\WPIDE\App\Controllers\FileManager\FileController@changeDirectory',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
            'read',
        ],
    ],
    [
        'route' => [
            'POST', '/getdir', '\WPIDE\App\Controllers\FileManager\FileController@getDirectory',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
            'read',
        ],
    ],
    [
        'route' => [
            'POST', '/getdirsize', '\WPIDE\App\Controllers\FileManager\FileController@getDirectorySize',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
            'read',
        ],
    ],
    [
        'route' => [
            'POST', '/copyitems', '\WPIDE\App\Controllers\FileManager\FileController@copyItems',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
            'read', 'write',
        ],
    ],
    [
        'route' => [
            'POST', '/moveitems', '\WPIDE\App\Controllers\FileManager\FileController@moveItems',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
            'read', 'write',
        ],
    ],
    [
        'route' => [
            'POST', '/renameitem', '\WPIDE\App\Controllers\FileManager\FileController@renameItem',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
            'read', 'write',
        ],
    ],
    [
        'route' => [
            'POST', '/zipitems', '\WPIDE\App\Controllers\FileManager\FileController@zipItems',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
            'read', 'write', 'zip',
        ],
    ],
    [
        'route' => [
            'POST', '/unzipitem', '\WPIDE\App\Controllers\FileManager\FileController@unzipItem',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
            'read', 'write', 'zip',
        ],
    ],
    [
        'route' => [
            'POST', '/deleteitems', '\WPIDE\App\Controllers\FileManager\FileController@deleteItems',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
            'read', 'write',
        ],
    ],
    [
        'route' => [
            'POST', '/createnew', '\WPIDE\App\Controllers\FileManager\FileController@createNew',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
            'read', 'write',
        ],
    ],
    [
        'route' => [
            'GET', '/upload', '\WPIDE\App\Controllers\FileManager\UploadController@chunkCheck',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
            'upload',
        ],
    ],
    [
        'route' => [
            'POST', '/upload', '\WPIDE\App\Controllers\FileManager\UploadController@upload',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
            'upload',
        ],
    ],
    [
        'route' => [
            'POST', '/revertimage', '\WPIDE\App\Controllers\FileManager\ImageController@revert',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
            'download',
        ],
    ],
    [
        'route' => [
            'GET', '/getimagestate', '\WPIDE\App\Controllers\FileManager\ImageController@getState',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
            'download',
        ],
    ],
    [
        'route' => [
            'GET', '/downloadimage', '\WPIDE\App\Controllers\FileManager\ImageController@download',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
            'download',
        ],
    ],
    [
        'route' => [
            'GET', '/download', '\WPIDE\App\Controllers\FileManager\DownloadController@download',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
            'download',
        ],
    ],
    [
        'route' => [
            'GET', '/readcontent', '\WPIDE\App\Controllers\FileManager\DownloadController@readContent',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
            'download',
        ],
    ],
    [
        'route' => [
            'GET', '/batchdownloadqueue', '\WPIDE\App\Controllers\FileManager\DownloadController@batchDownloadQueue',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
            'read', 'download', 'batchdownload',
        ],
    ],
    [
        'route' => [
            'POST', '/batchdownload', '\WPIDE\App\Controllers\FileManager\DownloadController@batchDownloadChunks',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
            'read', 'download', 'batchdownload',
        ],
    ],
    [
        'route' => [
            'DELETE', '/batchdownload', '\WPIDE\App\Controllers\FileManager\DownloadController@batchDownloadCancelled',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
            'read', 'download', 'batchdownload',
        ],
    ],
    [
        'route' => [
            'GET', '/batchdownload', '\WPIDE\App\Controllers\FileManager\DownloadController@batchDownloadStart',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
            'read', 'download', 'batchdownload',
        ],
    ],
    [
        'route' => [
            'POST', '/savecontent', '\WPIDE\App\Controllers\FileManager\FileController@saveContent',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
            'read', 'write',
        ],
    ],
    [
        'route' => [
            'GET', '/autosave/get', '\WPIDE\App\Controllers\FileManager\AutoSaveController@get',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
            'read',
        ],
    ],
    [
        'route' => [
            'POST', '/autosave/save', '\WPIDE\App\Controllers\FileManager\AutoSaveController@save',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
            'read', 'write'
        ],
    ],
    [
        'route' => [
            'POST', '/autosave/delete', '\WPIDE\App\Controllers\FileManager\AutoSaveController@delete',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
            'read', 'write'
        ],
    ],
    [
        'route' => [
            'GET', '/config/constants', '\WPIDE\App\Controllers\ConfigManager\ConfigController@getConfigConstants',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
            'read',
        ],
    ],
    [
        'route' => [
            'POST', '/config/constants/save', '\WPIDE\App\Controllers\ConfigManager\ConfigController@saveConfigConstant',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
            'read', 'write'
        ],
    ],
    [
        'route' => [
            'POST', '/config/constants/delete', '\WPIDE\App\Controllers\ConfigManager\ConfigController@deleteConfigConstant',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
            'read', 'write'
        ],
    ],
    [
        'route' => [
            'POST', '/images/optimizer', '\WPIDE\App\Controllers\FileManager\ImageOptimizerController@status',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
            'read',
        ],
    ],
    [
        'route' => [
            'POST', '/images/optimizer/settings', '\WPIDE\App\Controllers\FileManager\ImageOptimizerController@saveSettings',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
            'read', 'write'
        ],
    ],
    [
        'route' => [
            'POST', '/images/optimizer/pending', '\WPIDE\App\Controllers\FileManager\ImageOptimizerController@pending',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
            'read',
        ],
    ],
    [
        'route' => [
            'POST', '/images/optimizer/optimize', '\WPIDE\App\Controllers\FileManager\ImageOptimizerController@optimize',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
            'read', 'write'
        ],
    ],
    [
        'route' => [
            'POST', '/images/optimizer/restore', '\WPIDE\App\Controllers\FileManager\ImageOptimizerController@restore',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
            'read', 'write'
        ],
    ],
    [
        'route' => [
            'POST', '/images/optimizer/replace', '\WPIDE\App\Controllers\FileManager\ImageOptimizerController@replace',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
            'read', 'write'
        ],
    ],
    [
        'route' => [
            'POST', '/db/searchreplace/tables', '\WPIDE\App\Controllers\DbManager\SearchReplaceController@tables',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
            'read',
        ],
    ],
    [
        'route' => [
            'POST', '/db/searchreplace/step', '\WPIDE\App\Controllers\DbManager\SearchReplaceController@step',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
            'read', 'write'
        ],
    ],
    [
        'route' => [
            'POST', '/db/searchreplace/start', '\WPIDE\App\Controllers\DbManager\SearchReplaceController@start',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
            'read', 'write'
        ],
    ],
    [
        'route' => [
            'POST', '/db/searchreplace/finish', '\WPIDE\App\Controllers\DbManager\SearchReplaceController@finish',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
            'read', 'write'
        ],
    ],
    [
        'route' => [
            'POST', '/db/searchreplace/undo', '\WPIDE\App\Controllers\DbManager\SearchReplaceController@undo',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
            'read', 'write'
        ],
    ],
    [
        'route' => [
            'POST', '/db/searchreplace/forget', '\WPIDE\App\Controllers\DbManager\SearchReplaceController@forget',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
            'read', 'write'
        ],
    ],
    [
        'route' => [
            'POST', '/db/gettables', '\WPIDE\App\Controllers\DbManager\DatabaseController@getTables',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
            'read',
        ],
    ],
    [
        'route' => [
            'POST', '/db/gettablerows', '\WPIDE\App\Controllers\DbManager\DatabaseController@getTableRows',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
            'read',
        ],
    ],
    [
        'route' => [
            'POST', '/db/gettablestructure', '\WPIDE\App\Controllers\DbManager\DatabaseController@getTableStructureRows',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
            'read',
        ],
    ],
    [
        'route' => [
            'POST', '/db/savetablerow', '\WPIDE\App\Controllers\DbManager\DatabaseController@saveTableRow',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
            'read', 'write'
        ],
    ],
    [
        'route' => [
            'POST', '/db/deletetablerows', '\WPIDE\App\Controllers\DbManager\DatabaseController@deleteTableRows',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
            'read', 'write'
        ],
    ],
    [
        'route' => [
            'POST', '/db/droptable', '\WPIDE\App\Controllers\DbManager\DatabaseController@dropTable',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
            'read', 'write'
        ],
    ],
    [
        'route' => [
            'POST', '/db/emptytable', '\WPIDE\App\Controllers\DbManager\DatabaseController@emptyTable',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
            'read', 'write'
        ],
    ],
    [
        'route' => [
            'POST', '/db/createtable', '\WPIDE\App\Controllers\DbManager\DatabaseController@createTable',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
            'read', 'write'
        ],
    ],
    [
        'route' => [
            'GET', '/db/exporttable', '\WPIDE\App\Controllers\DbManager\DatabaseController@exportTable',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
            'read', 'write'
        ],
    ],
    [
        'route' => [
            'GET', '/db/exportdb', '\WPIDE\App\Controllers\DbManager\DatabaseController@exportDatabase',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
            'read', 'write'
        ],
    ],
    [
        'route' => [
            'GET', '/snippets/cloud', '\WPIDE\App\Controllers\Snippets\CloudController@status',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
        ],
    ],
    [
        'route' => [
            'POST', '/snippets/cloud/connect', '\WPIDE\App\Controllers\Snippets\CloudController@connect',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
        ],
    ],
    [
        'route' => [
            'POST', '/snippets/cloud/disconnect', '\WPIDE\App\Controllers\Snippets\CloudController@disconnect',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
        ],
    ],
    [
        'route' => [
            'POST', '/snippets/cloud/dashboard', '\WPIDE\App\Controllers\Snippets\CloudController@dashboard',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
        ],
    ],
    [
        'route' => [
            'POST', '/snippets/cloud/rotate', '\WPIDE\App\Controllers\Snippets\CloudController@rotate',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
        ],
    ],
    [
        'route' => [
            'POST', '/snippets/cloud/sync', '\WPIDE\App\Controllers\Snippets\CloudController@sync',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
        ],
    ],
    [
        'route' => [
            'POST', '/snippets/cloud/library', '\WPIDE\App\Controllers\Snippets\CloudController@library',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
        ],
    ],
    [
        'route' => [
            'POST', '/snippets/cloud/upload', '\WPIDE\App\Controllers\Snippets\CloudController@upload',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
        ],
    ],
    [
        'route' => [
            'POST', '/snippets/cloud/import', '\WPIDE\App\Controllers\Snippets\CloudController@import',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
        ],
    ],
    [
        'route' => [
            'POST', '/snippets/cloud/ai', '\WPIDE\App\Controllers\Snippets\CloudController@ai',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
        ],
    ],
    [
        'route' => [
            'GET', '/snippets/tracking', '\WPIDE\App\Controllers\Snippets\TrackingController@bootstrap',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
        ],
    ],
    [
        'route' => [
            'POST', '/snippets/tracking/save', '\WPIDE\App\Controllers\Snippets\TrackingController@save',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
        ],
    ],
    [
        'route' => [
            'POST', '/snippets/tracking/test', '\WPIDE\App\Controllers\Snippets\TrackingController@test',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
        ],
    ],
    [
        'route' => [
            'POST', '/snippets/tracking/log', '\WPIDE\App\Controllers\Snippets\TrackingController@log',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
        ],
    ],
    [
        'route' => [
            'GET', '/snippets/bootstrap', '\WPIDE\App\Controllers\Snippets\SnippetController@bootstrap',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
        ],
    ],
    [
        'route' => [
            'POST', '/snippets/list', '\WPIDE\App\Controllers\Snippets\SnippetController@list',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
        ],
    ],
    [
        'route' => [
            'POST', '/snippets/pin', '\WPIDE\App\Controllers\Snippets\SnippetController@pin',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
        ],
    ],
    [
        'route' => [
            'POST', '/snippets/get', '\WPIDE\App\Controllers\Snippets\SnippetController@get',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
        ],
    ],
    [
        'route' => [
            'POST', '/snippets/save', '\WPIDE\App\Controllers\Snippets\SnippetController@save',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
        ],
    ],
    [
        'route' => [
            'POST', '/snippets/validate', '\WPIDE\App\Controllers\Snippets\SnippetController@validate',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
        ],
    ],
    [
        'route' => [
            'POST', '/snippets/status', '\WPIDE\App\Controllers\Snippets\SnippetController@status',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
        ],
    ],
    [
        'route' => [
            'POST', '/snippets/bulk', '\WPIDE\App\Controllers\Snippets\SnippetController@bulk',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
        ],
    ],
    [
        'route' => [
            'POST', '/snippets/execute', '\WPIDE\App\Controllers\Snippets\SnippetController@execute',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
        ],
    ],
    [
        'route' => [
            'POST', '/snippets/revision', '\WPIDE\App\Controllers\Snippets\SnippetController@revision',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
        ],
    ],
    [
        'route' => [
            'POST', '/snippets/revision/restore', '\WPIDE\App\Controllers\Snippets\SnippetController@restoreRevision',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
        ],
    ],
    [
        'route' => [
            'POST', '/snippets/events', '\WPIDE\App\Controllers\Snippets\SnippetController@events',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
        ],
    ],
    [
        'route' => [
            'POST', '/snippets/events/clear', '\WPIDE\App\Controllers\Snippets\SnippetController@clearEvents',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
        ],
    ],
    [
        'route' => [
            'POST', '/snippets/testing', '\WPIDE\App\Controllers\Snippets\SnippetController@testing',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
        ],
    ],
    [
        'route' => [
            'POST', '/snippets/settings', '\WPIDE\App\Controllers\Snippets\SnippetController@settings',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
        ],
    ],
    [
        'route' => [
            'POST', '/snippets/search', '\WPIDE\App\Controllers\Snippets\SnippetController@search',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
        ],
    ],
    [
        'route' => [
            'POST', '/snippets/export', '\WPIDE\App\Controllers\Snippets\ToolsController@export',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
        ],
    ],
    [
        'route' => [
            'POST', '/snippets/import/analyze', '\WPIDE\App\Controllers\Snippets\ToolsController@analyzeImport',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
        ],
    ],
    [
        'route' => [
            'POST', '/snippets/import/commit', '\WPIDE\App\Controllers\Snippets\ToolsController@commitImport',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
        ],
    ],
    [
        'route' => [
            'POST', '/snippets/library', '\WPIDE\App\Controllers\Snippets\ToolsController@library',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
        ],
    ],
    [
        'route' => [
            'POST', '/snippets/library/get', '\WPIDE\App\Controllers\Snippets\ToolsController@libraryItem',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
        ],
    ],
    [
        'route' => [
            'POST', '/snippets/library/favorite', '\WPIDE\App\Controllers\Snippets\ToolsController@libraryFavorite',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
        ],
    ],
    [
        'route' => [
            'POST', '/snippets/library/install', '\WPIDE\App\Controllers\Snippets\ToolsController@libraryInstall',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
        ],
    ],
    [
        'route' => [
            'GET', '/snippets/packs', '\WPIDE\App\Controllers\Snippets\ToolsController@packs',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
        ],
    ],
    [
        'route' => [
            'POST', '/snippets/packs/install', '\WPIDE\App\Controllers\Snippets\ToolsController@installPack',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
        ],
    ],
    [
        'route' => [
            'POST', '/snippets/packs/remove', '\WPIDE\App\Controllers\Snippets\ToolsController@removePack',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
        ],
    ],
    [
        'route' => [
            'GET', '/snippets/library/account', '\WPIDE\App\Controllers\Snippets\ToolsController@libraryAccount',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
        ],
    ],
    [
        'route' => [
            'POST', '/snippets/library/publish', '\WPIDE\App\Controllers\Snippets\ToolsController@libraryPublish',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
        ],
    ],
    [
        'route' => [
            'POST', '/snippets/library/unpublish', '\WPIDE\App\Controllers\Snippets\ToolsController@libraryUnpublish',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
        ],
    ],
    [
        'route' => [
            'POST', '/snippets/library/update', '\WPIDE\App\Controllers\Snippets\ToolsController@libraryUpdate',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
        ],
    ],
    [
        'route' => [
            'POST', '/snippets/generators/get', '\WPIDE\App\Controllers\Snippets\ToolsController@generator',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
        ],
    ],
    [
        'route' => [
            'POST', '/snippets/generators/generate', '\WPIDE\App\Controllers\Snippets\ToolsController@generate',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
        ],
    ],
    [
        'route' => [
            'GET', '/snippets/global', '\WPIDE\App\Controllers\Snippets\ToolsController@globalScripts',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
        ],
    ],
    [
        'route' => [
            'POST', '/snippets/global/save', '\WPIDE\App\Controllers\Snippets\ToolsController@saveGlobalScripts',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
        ],
    ],
    [
        'route' => [
            'POST', '/snippets/global/revision', '\WPIDE\App\Controllers\Snippets\ToolsController@globalRevision',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
        ],
    ],
    [
        'route' => [
            'GET', '/snippets/files', '\WPIDE\App\Controllers\Snippets\ToolsController@files',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
        ],
    ],
    [
        'route' => [
            'POST', '/snippets/files/save', '\WPIDE\App\Controllers\Snippets\ToolsController@saveFile',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
        ],
    ],
    [
        'route' => [
            'POST', '/snippets/scss', '\WPIDE\App\Controllers\Snippets\ToolsController@scss',
        ],
        'roles' => [
            'user', 'admin',
        ],
        'permissions' => [
        ],
    ],
];
