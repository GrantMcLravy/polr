<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateClicksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('clicks', function(Blueprint $table)
        {
            $table->engine = 'InnoDB';

            $table->increments('id');
            $table->string('ip');
            $table->string('country')->nullable();
            $table->string('referer')->nullable();
            $table->string('referer_host')->nullable();
            $table->text('user_agent')->nullable();
            $table->integer('link_id')->unsigned();

            $table->index('ip');
            $table->index('referer_host');
            $table->index('link_id');
            $table->foreign('link_id')->references('id')->on('links')->onDelete('cascade');

            // Laravel 5.1's timestamps(): NOT NULL with a zero-date default
            // (Laravel 13's timestamps() would create nullable columns).
            $table->timestamp('created_at')->default('0000-00-00 00:00:00');
            $table->timestamp('updated_at')->default('0000-00-00 00:00:00');
        });
    }
    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('clicks');
    }
}
