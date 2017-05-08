<?php
/**
 * User: leon
 * Date: 2017/5/8 0008  下午 2:56
 */

namespace VendorWechat\Console\Commands;

use Illuminate\Console\Command;
use VendorWechat\Http\Controllers\KangshifuController;

class KsfClearLock extends Command
{
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'ksf:clear-lock';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'kangshifu clear huo li lock';

	/**
	 * Execute the console command.
	 *
	 * @return mixed
	 */
	public function handle()
	{
		KangshifuController::clearLockDaily();
		return;
	}
}
