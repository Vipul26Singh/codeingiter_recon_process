<?php
namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;

class Reconcilation extends BaseCommand
{
    protected $group = 'cron';
    protected $name = 'recon:'.TRXN_TABLENAME;
    protected $description = 'Reconcile the ' . TRXN_TABLENAME . ' database.';


    private $db;
    private $job_id = null;
    private $table_name = TRXN_TABLENAME;
    private $row_per_batch = 10000;
    private $max_batch_per_run = 20;
    private $enable_log = True;
    private $enable_updated_log = false;
    private $enable_stdlog = false;
    public function run(array $params)
    {
        $this->log("Command Started");
        $this->db = \Config\Database::connect();
        // check if disabled
        if (!$this->enabled()) {
            $this->log("Disabled from database");
            return EXIT_USER_INPUT;
        }
        // check if it safe to run 
        if (!$this->isSafe()) {
            $this->log("Unfinished command");
            return EXIT_USER_INPUT;
        }
        $batchNum = 1;
        while ($this->batchProcess($batchNum)) {
            $batchNum += 1;
            if ($batchNum > $this->max_batch_per_run) {
                break;
            }
            if (!$this->isSafe()) {
                $this->log("Another process has started processing");
                return EXIT_USER_INPUT;
            }
        }
    }

    private function batchProcess($batchNum)
    {
        $this->log("Processing batch: $batchNum ");
        $this->createLock();
        $agent_ids = $this->getAgentIds();
        if (empty($agent_ids)) {
            $this->log("Exiting... Noting to process in the batch: $batchNum ");
            return False;
        }
        foreach ($agent_ids as $agent) {
            $this->log("Processing for agent {$agent->txn_agentid} ");
            $this->processAgent($agent->txn_agentid);
        }
        return True;
    }

    private function enabled()
    {
        $enabled = $this->db->query("select enabled from command_enabler where command_name = '{$this->name}'")->getResult();
        if (empty($enabled) || empty($enabled[0]->enabled)) {
            return 0;
        }
        return 1;
    }

    private function log($message, $type = "error")
    {
        if (empty($this->job_id)) {
            $this->job_id = uniqid();
        }
        $finalMsg = date("Y-m-d h:i:s") . "  Job: " . $this->name . " Jobid: " . $this->job_id . " Data: {" . $message . "}";
        if ($this->enable_stdlog) {
            echo $finalMsg . "\n";
        }

        if ($this->enable_log) {
            log_message($type, $finalMsg);
        }
    }

    private function processAgent($agent_id)
    {
        $correctOpeningBalance = $this->getLastClosingBalance($agent_id);
        $batchData = $this->db->query("select * from {$this->table_name} where recon_status = 'P' and txn_agentid = '{$agent_id}' order by txn_id asc")->getResult();
        $totalRows = 0;
        $updateRows = 0;
        foreach ($batchData as $data) {
            $totalRows += 1;
            $newClosingBalance = $data->txn_clbal;
            $builder = $this->db->table($this->table_name);
            $newData = [
                'recon_status' => 'D'
            ];
            if ($data->txn_opbal != $correctOpeningBalance) {
                $updateRows += 1;
                $newClosingBalance = $correctOpeningBalance + $data->txn_crdt - $data->txn_dbdt;
                $newData['txn_opbal'] = $correctOpeningBalance;
                $newData['txn_clbal'] = $newClosingBalance;
                $newData['is_recon_updated'] = 'Y';
                if ($this->enable_updated_log) {
                    $this->log("Updating details: txn_id: {$data->txn_id} agent_id: {$data->txn_agentid} old_opening: {$data->txn_opbal} old_closing: {$data->txn_clbal} new_opening: {$correctOpeningBalance} new_closing: {$newClosingBalance}");
                }
            }
            $builder->where('txn_id', $data->txn_id);
            $builder->update($newData);
            $correctOpeningBalance = $newClosingBalance;
        }
        $this->log("Total processed rows: {$totalRows}");
        $this->log("Updated rows: {$updateRows}");
    }

    private function getLastClosingBalance($agent_id)
    {
        $opening_bal = 0;
        $agentLastProcessedRow = $this->db->query("select * from {$this->table_name} where txn_agentid = '{$agent_id}' and recon_status = 'D' order by txn_id desc limit 1")->getResult();
        // Consider first opening bala
        if (empty($agentLastProcessedRow)) {
            $agentLastProcessedRow = $this->db->query("select * from {$this->table_name} where txn_agentid = '{$agent_id}' and recon_status = 'P' order by txn_id asc limit 1")->getResult();
            $opening_bal = $agentLastProcessedRow[0]->txn_opbal;
        } else {
            $opening_bal = $agentLastProcessedRow[0]->txn_clbal;
        }
        return $opening_bal;
    }

    private function getAgentIds()
    {
        return $this->db->query("select distinct txn_agentid from {$this->table_name} where recon_status = 'P'")->getResult();
    }

    private function isSafe()
    {
        $pendingData = $this->db->query("select * from {$this->table_name} where recon_status = 'P'")->getResult();
        return empty($pendingData);
    }

    private function createLock()
    {
        $this->db->query("update {$this->table_name} set recon_status = 'P' where recon_status = 'I' order by txn_id asc limit {$this->row_per_batch} ");
    }
}
