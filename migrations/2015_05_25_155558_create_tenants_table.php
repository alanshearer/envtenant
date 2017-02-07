<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTenantsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tenants', function(Blueprint $table)
        {

            $table->increments('id');
            $table->string('name')->unique()->nullable();
            $table->string('email')->index()->nullable();
            $table->string('subdomain')->unique()->nullable();
            $table->string('alias_domain')->unique()->nullable();
            $table->string('connection');
            $table->text('meta');

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('tenants');
    }
}
