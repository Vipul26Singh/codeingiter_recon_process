<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CommandEnabler extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 5,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'command_name' => [
                'type' => 'VARCHAR',
                'constraint' => '100',
            ],
            'enabled' => [
                'type' => 'TINYINT',
                'default' => 1,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('command_enabler');
    }

    public function down()
    {
        //
    }
}
