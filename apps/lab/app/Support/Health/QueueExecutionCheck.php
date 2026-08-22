<?php

namespace App\Support\Health;

use App\Jobs\LabQueueProbe;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;

final class QueueExecutionCheck extends AbstractHealthCheck
{
    public function number(): int
    {
        return 3;
    }

    public function name(): string
    {
        return 'Queue';
    }

    public function target(): string
    {
        return 'queue';
    }

    public function expectation(): string
    {
        return CheckResult::MUST_SUCCEED;
    }

    public function run(): CheckResult
    {
        DB::table('lab_job_probes')->where('id', LabQueueProbe::PROBE_ID)->delete();

        $job = (new LabQueueProbe)->onQueue('lab-health');
        Bus::dispatch($job);

        $process = Process::timeout(30)->run([
            PHP_BINARY,
            base_path('artisan'),
            'queue:work',
            '--once',
            '--stop-when-empty',
            '--queue=lab-health',
            '--sleep=0',
        ]);

        if (! $process->successful()) {
            return $this->fail('queue worker exited unsuccessfully: '.trim($process->errorOutput()));
        }

        $probe = DB::table('lab_job_probes')->where('id', LabQueueProbe::PROBE_ID)->first();
        if ($probe === null || $probe->ran_at === null) {
            return $this->fail('queue worker exited without executing LabQueueProbe');
        }

        if ((int) $probe->worker_pid === getmypid()) {
            return $this->fail('queue probe ran in the dispatcher process, not an exited worker');
        }

        return $this->pass("LabQueueProbe executed by worker pid {$probe->worker_pid}, then worker exited");
    }
}
