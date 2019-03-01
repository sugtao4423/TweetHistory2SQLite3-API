<?php
declare(strict_types=1);
define('TWEET_DB', dirname(__FILE__) . '/../TweetHistory2SQLite3/tweets.sqlite3');
define('TWEET_BASE_QUERY',
'SELECT
statuses.id, statuses.text, statuses.created_at, statuses.in_reply_to_status_id, statuses.in_reply_to_user_id, statuses.in_reply_to_screen_name, statuses.geo,
users.id AS user_id, users.name AS user_name, users.screen_name AS user_screen_name, users.protected AS user_protected, users.profile_image_url_https AS user_profile_image_url_https, users.verified AS user_verified,
statuses.user_mention_ids,
statuses.media_ids,
statuses.url_ids,
sources.name AS source_name, sources.url AS source_url,
retweeted_statuses.id AS retweeted_status_id, retweeted_statuses.text AS retweeted_status_text, retweeted_statuses.created_at AS retweeted_status_created_at, retweeted_statuses.in_reply_to_status_id AS retweeted_status_in_reply_to_status_id, retweeted_statuses.in_reply_to_user_id AS retweeted_status_in_reply_to_user_id, retweeted_statuses.in_reply_to_screen_name AS retweeted_status_in_reply_to_screen_name, retweeted_statuses.geo AS retweeted_status_geo,
retweetedUser.id AS retweeted_status_user_id, retweetedUser.name AS retweeted_status_user_name, retweetedUser.screen_name AS retweeted_status_user_screen_name, retweetedUser.protected AS retweeted_status_user_protected, retweetedUser.profile_image_url_https AS retweeted_status_user_profile_image_url_https, retweetedUser.verified AS retweeted_status_user_verified,
retweeted_statuses.user_mention_ids AS retweeted_status_user_mention_ids,
retweeted_statuses.media_ids AS retweeted_status_media_ids,
retweeted_statuses.url_ids AS retweeted_status_url_ids,
retweetedSource.name AS retweeted_status_source_name, retweetedSource.url AS retweeted_status_source_url
FROM statuses
INNER JOIN users ON statuses.user_id = users.id
INNER JOIN sources ON statuses.source_id = sources.id
LEFT OUTER JOIN retweeted_statuses ON statuses.retweeted_status_id = retweeted_statuses.id
LEFT OUTER JOIN users AS retweetedUser ON retweeted_statuses.user_id = retweetedUser.id
LEFT OUTER JOIN sources AS retweetedSource ON retweeted_statuses.source_id = retweetedSource.id');

header('Content-Type: application/json');

if(!file_exists(TWEET_DB)){
    echo '[]';
    exit(1);
}

$type  = isset($_GET['t']) ? $_GET['t'] : 'lasttweet';
$page  = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$count = isset($_GET['c']) ? (int)$_GET['c'] : 50;
$query = isset($_GET['q']) ? $_GET['q'] : null;
switch($type){
    case 'lasttweet':
        lastTweet($page, $count);
        break;
    case 'search':
        if(is_null($query)){
            echo '[]';
        }else{
            searchTweet($query, $page, $count);
        }
        break;
}

function lastTweet(int $page, int $count){
    $offset = $page * $count;

    $db = new SQLite3(TWEET_DB);
    $sql = TWEET_BASE_QUERY . " WHERE statuses.ROWID BETWEEN
        (SELECT MAX(statuses.ROWID) FROM statuses) - ${offset} + 1 AND
        (SELECT MAX(statuses.ROWID) FROM statuses) - ${offset} + ${count}";
    echo getStatusesJson($db, $sql);
    $db->close();
}

function searchTweet(string $query, int $page, int $count){
    $offset = ($page - 1) * $count;
    $query = str_replace("'", "''", $query);

    $db = new SQLite3(TWEET_DB);
    $sql = TWEET_BASE_QUERY . " WHERE statuses.text LIKE '%${query}%'
        ORDER BY statuses.id DESC
        LIMIT ${count} OFFSET ${offset}";
    echo getStatusesJson($db, $sql, true);
    $db->close();
}

function getStatusesJson(SQLite3 $db, string $sql, bool $isReverse = false): string{
    $result = [];
    $query = $db->query($sql);
    while($q = $query->fetchArray(SQLITE3_ASSOC)){
        $status = queryResult2Array($db, $q);

        $isRetweetExists = isset($q['retweeted_status_id']);
        if($isRetweetExists){
            $status = array_merge($status, [
                'retweeted_status' => queryResult2Array($db, $q, true)
            ]);
        }

        $result[] = $status;
    }
    $result = $isReverse ? array_reverse($result) : $result;
    return json_encode($result, JSON_UNESCAPED_UNICODE);
}

function int2bool(int $i): bool{
    return $i === 1;
}

function queryResult2Array(SQLite3 $db, array $queryResult, bool $isRetweet = false): array{
    $rt = $isRetweet ? 'retweeted_status_' : '';

    $id                         = $queryResult["${rt}id"];
    $text                       = $queryResult["${rt}text"];
    $createdAt                  = $queryResult["${rt}created_at"];
    $inReplyToStatusId          = $queryResult["${rt}in_reply_to_status_id"];
    $inReplyToUserId            = $queryResult["${rt}in_reply_to_user_id"];
    $inReplyToScreenName        = $queryResult["${rt}in_reply_to_screen_name"];
    $geo                        = $queryResult["${rt}geo"];
    $userId                     = $queryResult["${rt}user_id"];
    $userName                   = $queryResult["${rt}user_name"];
    $userScreenName             = $queryResult["${rt}user_screen_name"];
    $userProtected              = int2bool($queryResult["${rt}user_protected"]);
    $userProfileImageUrlHttps   = $queryResult["${rt}user_profile_image_url_https"];
    $userVerified               = int2bool($queryResult["${rt}user_verified"]);
    $sourceName                 = $queryResult["${rt}source_name"];
    $sourceUrl                  = $queryResult["${rt}source_url"];

    $userMentionIds = is_null($queryResult["${rt}user_mention_ids"])  ? [] : explode(',', $queryResult["${rt}user_mention_ids"]);
    $mediaIds       = is_null($queryResult["${rt}media_ids"])         ? [] : explode(',', $queryResult["${rt}media_ids"]);
    $urlIds         = is_null($queryResult["${rt}url_ids"])           ? [] : explode(',', $queryResult["${rt}url_ids"]);

    $arr = [
        'id_str' => (string)$id,
        'text' => $text,
        'created_at' => (string)$createdAt,
    ];

    if(isset($inReplyToStatusId)){
        $arr = array_merge($arr, [
            'in_reply_to_status_id_str' => (string)$inReplyToStatusId,
            'in_reply_to_user_id_str' => (string)$inReplyToUserId,
            'in_reply_to_screen_name' => $inReplyToScreenName
        ]);
    }

    if(isset($geo)){
        $arr = array_merge($arr, [
            'geo' => [
                'type' => 'Point',
                'coordinates' => [
                    explode(',', $geo)[0],
                    explode(',', $geo)[1]
                ]
            ]
        ]);
    }

    $arr = array_merge($arr, [
        'user' => [
            'id_str' => (string)$userId,
            'name' => $userName,
            'screen_name' => $userScreenName,
            'protected' => $userProtected,
            'profile_image_url_https' => $userProfileImageUrlHttps,
            'verified' => $userVerified
        ],
        'source' => "<a href=\"${sourceUrl}\" rel=\"nofollow\">${sourceName}</a>"
    ]);

    $userMentions = getMentionUsers($db, $userMentionIds);
    $medias = getMedias($db, $mediaIds);
    $urls = geturls($db, $urlIds);
    $arr = array_merge($arr, [
        'entities' => [
            'user_mentions' => $userMentions,
            'media' => $medias,
            'urls' => $urls
        ]
    ]);

    return $arr;
}

function getMentionUsers(SQLite3 $db, array $userMentionIds): array{
    if(count($userMentionIds) < 1){
        return [];
    }
    $result = [];
    $sql = 'SELECT * FROM user_mentions WHERE user_mentions.id = ' . $userMentionIds[0];
    for($i = 1; $i < count($userMentionIds); $i++){
        $sql .= " OR user_mentions.id = {$userMentionIds[$i]}";
    }
    $query = $db->query($sql);
    while($q = $query->fetchArray(SQLITE3_ASSOC)){
        $result[] = [
            'id_str' => (string)$q['id'],
            'name' => $q['name'],
            'screen_name' => $q['screen_name']
        ];
    }
    return $result;
}

function getMedias(SQLite3 $db, array $mediaIds): array{
    if(count($mediaIds) < 1){
        return [];
    }
    $result = [];
    $sql = 'SELECT * FROM medias WHERE medias.id = ' . $mediaIds[0];
    for($i = 1; $i < count($mediaIds); $i++){
        $sql .= " OR medias.id = {$mediaIds[$i]}";
    }
    $query = $db->query($sql);
    while($q = $query->fetchArray(SQLITE3_ASSOC)){
        $result[] = [
            'id_str' => (string)$q['id'],
            'url' => $q['url'],
            'media_url_https' => $q['media_url_https'],
            'display_url' => $q['display_url'],
            'expanded_url' => $q['expanded_url'],
            'media_alt' => $q['media_alt']
        ];
    }
    return $result;
}

function getUrls(SQLite3 $db, array $urlIds): array{
    if(count($urlIds) < 1){
        return [];
    }
    $result = [];
    $sql = 'SELECT * FROM urls WHERE urls.id = ' . $urlIds[0];
    for($i = 1; $i < count($urlIds); $i++){
        $sql .= " OR urls.id = {$urlIds[$i]}";
    }
    $query = $db->query($sql);
    while($q = $query->fetchArray(SQLITE3_ASSOC)){
        $result[] = [
            'url' => $q['url'],
            'display_url' => $q['display_url'],
            'expanded_url' => $q['expanded_url']
        ];
    }
    return $result;
}
