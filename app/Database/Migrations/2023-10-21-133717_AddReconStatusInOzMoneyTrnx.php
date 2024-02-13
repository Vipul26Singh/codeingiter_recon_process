<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddReconStatusInOzMoneyTrnx extends Migration
{
    public function up()
    {
        $table = TRXN_TABLENAME;
        $fields = [
            'recon_status' => [
                'type' => 'CHAR',
                'constraint' => '2',
                'default' => 'I'
            ],
            'is_recon_updated' => [
                'type' => 'CHAR',
                'constraint' => '2',
                'default' => 'N'
            ],
        ];
        $this->forge->addColumn($table, $fields);
        $this->forge->addKey('recon_status');
        $this->forge->processIndexes($table);*/
        //$this->db->query("update $table set recon_status = 'D'");
        //$this->db->query("update $table set is_recon_updated = 'N'");
    }

    public function down()
    {
        //
    }
}
