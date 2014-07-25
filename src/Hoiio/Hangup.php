<?php


namespace Hoiio;

use Hoiio\Log\Entry as LogEntry;
use Hoiio\Log\Repository as LogRepository;
use Hoiio\Aggregate\Entry as AggEntry;
use Hoiio\Aggregate\Repository as AggRepository;
use Hoiio\Log\Pusher as LogPusher;
use PDO;
use DateTime;
use Hoiio\AccountCounter\Repository as ACRepo;
use Hoiio\AccountCounter\Entry as ACEntry;
use Hoiio\Alert\Sender as AlertSender;
use Hoiio\Alert\Repository as AlertRepo;
use Hoiio\Lockable;
use OnCall\Bundle\AdminBundle\Model\Timezone;

require_once(__DIR__ . '/../../vendor/hoiio-php/Services/HoiioService.php');

class Hangup
{
    protected $pdo;
    protected $zmq;

    public function __construct(PDO $pdo, $redis, $zmq)
    {
        parent::__construct($redis);
        $this->pdo = $pdo;
        $this->zmq = $zmq;
    }

    protected function updateLog(LogEntry $log, $params)
    {
        $log->setBillRate($params->getBillRate())
            ->setBillDuration($params->getBillDuration())
            ->setDuration($params->getDuration())
            ->setDateStart(new DateTime($params->getStartTime()))
            ->setDateEnd(new DateTime($params->getEndTime()))
            ->setStatus($params->getStatus())
            ->setHangupCause($params->getHangupCause());

        return $log;
    }
	
    public function run($post, $mail_config, $notifyURL, $appID, $accessToken)
    {
        try
        {
            // parse parameters
            $params = new Parameters($post);

            // lock call_id
            $this->lock($params->getUniqueID());
			
			// Calling Hoiio API
			$session = $params->getSession();
			$msg = "Call is about to hangup";
			$tag = "hangup_tag";
			
			$hoiioService = new HoiioService($appId, $accessToken);
			$hoiioService->ivrHangup($session, $notifyURL, $msg, $tag);

			
            // start log and aggregate

            // get log
            $log_repo = new LogRepository($this->pdo);
            $log = $log_repo->find($params->getUniqueID());
            // no log entry found (no answer call?)
            if ($log == null)
            {
                error_log('no answer entry');
                $this->unlock($params->getUniqueID());
                exit;
            }

            // update log with hangup data
            $this->updateLog($log, $params);
            $log_repo->updateHangup($log);

            // alerts
            $al_repo = new AlertRepo($this->pdo);
            $al_sender = new AlertSender($al_repo, $mail_config);
            $al_sender->send($log);

            // aggregate
            $agg_repo = new AggRepository($this->pdo);
            $agg = AggEntry::createFromLog($log);
            $agg_repo->persist($agg);

            // get client timezone
            $tzone = $log_repo->getClientTimezone($log->getClientID());
            $cl_tzone = $cl_tzone = Timezone::toPHPTimezone($tzone);
            $log->getDateStart()->setTimezone($cl_tzone);

            // live log
            $log_pusher = new LogPusher($this->zmq);
            $log_pusher->send($log);

            // TODO: account counter
            /*
            $num_data = $qmsg->getNumberData();
            $ac_repo = new ACRepo($this->pdo);
            $ac_entry = new ACEntry(new DateTime(), $num_data['user_id']);
            $ac_entry->setCall(1);
            $ac_entry->setDuration($qmsg->getHangupParams()->getDuration());
            $ac_repo->append($ac_entry);
            */

            // unlock call_id
            $this->unlock($params->getUniqueID());

            // end log and aggregate
        }
        catch (PDOException $e)
        {
            // catch pdo / db error
            error_log('pdo exception');
        }
    }
}