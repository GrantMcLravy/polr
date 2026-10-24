<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function(Blueprint $table)
        {
            $table->increments('id');

            $table->string('username')->unique();
            $table->string('password');
            $table->string('email');
            $table->text('ip');

            $table->string('recovery_key');
            $table->string('role');
            $table->string('active');

            $table->string('api_key')->nullable();
            $table->boolean('api_active')->default(0);
            $table->string('api_quota')->default(60);

            // Laravel 5.1's timestamps(): NOT NULL with a zero-date default
            // (Laravel 13's timestamps() would create nullable columns).
            $table->timestamp('created_at')->default('0000-00-00 00:00:00');
            $table->timestamp('updated_at')->default('0000-00-00 00:00:00');
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('users');
    }
}
