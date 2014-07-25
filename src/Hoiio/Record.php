<?php

namespace Hoiio;
echo "test";
use Hoiio\Log\Repository as LogRepository;
use PDO;

require_once('/../../vendor/hoiio-php/Services/HoiioService.php');

class Record
{
    protected $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function run($post, $notifyURL, $appID, $accessToken)
    {
        // get params
		$params = new Parameters($post);
        $call_id = $params->getUniqueID();
        $audio_url = $params->getRecordUrl();

		// Calling Hoiio record service
        $session = $params->getSession();
        $msg = "About to start recording";
        $maxDuration = 30;
        $tag = "recording_tag";

        $hoiioService = new HoiioService($appID, $accessToken);
        $hoiioService->ivrRecord($session, $notifyURL, $msg, $maxDuration, $tag);

		
        // update log
        $log_repo = new LogRepository($this->pdo);
        $log_repo->updateRecord($call_id, $audio_url);
    }
}


