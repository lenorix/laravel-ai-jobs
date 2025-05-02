<?php

namespace Lenorix\LaravelAiJobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Lenorix\LaravelAiJobs\Models\GptChatFuture;
use Lenorix\LaravelJobStatus\Traits\Trackable;
use MalteKuhr\LaravelGPT\GPTChat;

/**
 * Base to do `GPTChat::send()` in a job,
 * retrieving the result later.
 */
abstract class GptChatJob implements ShouldQueue
{
    use Queueable;
    use Trackable;

    public int $tries = 5;
    public array $messages;

    public function __construct(GPTChat $gptChat)
    {
        $this->messages = $gptChat->messages;
    }

    /**
     * Instance of a GPTChat to be used. The job will
     * supply the context messages to the instance.
     */
    abstract protected function getGptChatInstance(): GPTChat;

    public function handle(): void
    {
        $gptChat = $this->getGptChatInstance();
        $gptChat->messages = $this->messages;

        $gptChat->send();

        $this->setResult($gptChat->messages);
    }

    /**
     * Don't use it directly from the job, use `setResult()`
     *  to set the result instead.
     *
     * It's used to track a job after calling `dispatch()`
     *  like:
     *
     * ```php
     * $tracker = DummyJob::dispatch(2)
     *     ->getJob()
     *     ->tracker();
     * ```
     */
    public function tracker(): GptChatFuture
    {
        $this->track();

        return GptChatFuture::findOrFail($this->tracker->id);
    }
}
