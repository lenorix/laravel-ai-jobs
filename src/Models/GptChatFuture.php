<?php

namespace Lenorix\LaravelAiJobs\Models;

use Lenorix\LaravelJobStatus\Models\JobTracker;
use MalteKuhr\LaravelGPT\GPTChat;

class GptChatFuture extends JobTracker
{
    protected $table = 'job_trackers';

    public function getResultIn(GPTChat $gptChat): void
    {
        if (!$this->isSuccessful()) {
            throw new \Exception('Job is not successful, cannot update messages.');
        }
        $gptChat->messages = $this->result;
    }
}
