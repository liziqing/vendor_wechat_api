<?php
/**
 * User: leon
 * Date: 2017/5/15 0015  下午 3:01
 */

namespace VendorWechat\Console\Commands;

use Illuminate\Console\Command;
use VendorWechat\Http\Controllers\KangshifuController;

class KsfTmp extends Command
{
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'ksf:tmp';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'kangshifu tmp command';

	/**
	 * Execute the console command.
	 *
	 * @return mixed
	 */
	public function handle()
	{
//		KangshifuController::setAllHuoli();
		KangshifuController::delDb();
		return;
	}
}
