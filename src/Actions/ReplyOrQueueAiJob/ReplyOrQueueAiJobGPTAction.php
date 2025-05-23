<?php

namespace Lenorix\LaravelAiJobs\Actions\ReplyOrQueueAiJob;

use Closure;
use MalteKuhr\LaravelGPT\Generators\ChatPayloadGenerator;
use MalteKuhr\LaravelGPT\GPTChat;
use MalteKuhr\LaravelGPT\Shim\GPTActionShim;

class ReplyOrQueueAiJobGPTAction extends GPTActionShim
{
    public function __construct(
        protected GPTChat $gptChat,
    ) {}

    public function systemMessage(): ?string
    {
        $systemPrompt = $this->gptChat->systemMessage() ?? '';
        $functions = ChatPayloadGenerator::make($this->gptChat)->generate()['functions'] ?? [];

        return <<<'EOT'
You are a Fast/Slow Decision Controller. Given:

  1) The original system prompt
  2) Available functions/tools.
  3) Latest message history.

Decide fastly if the next assistant output requires tool execution or deeper reasoning (`queue` = true)
or is a simple reply (`queue` = false).

System prompt:

<sub-system-prompt>
EOT
        .$systemPrompt
        .<<<'EOT'
</sub-system-prompt>

Functions/tools:

<sub-functions>
EOT
        .json_encode($functions, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        .<<<'EOT'
</sub-functions>

Decide if reply using original system prompt and context (`sub-system-prompt`) or queue for deep reasoning and use tools.

Queue=true when:
  - Require calling/executing tools/functions.
  - Answer requires be longer than 600 characters.

Queue=false when:
  - greeting.
  - simple answers with knowledge from current context.
  - ask to user anything.

Mandatory Rules:
  • If message requires be longer than 600 characters, use `queue=true` and `message` = null.
  • Reply in `message` must be short, less than 600 characters, if more, use queue=true.
  • Reply `message` can not tell it will do an action or call a function/tool if `queue` is false.
  • If `queue` = false, `message` is the exact assistant reply.
  • If `message` ask anything or require more details, use queue=false.
  • Only emit JSON.

Examples:

  1) Greeting:
     User Input: "Hello!"
     Assistant Output: "Hi there!"
     -> {"queue": false, "message": "Hi there!"}

  2) Tool request or internal reasoning:
     Assistant Output: "Let me fetch the latest data for you."
     -> {"queue": true, "message": "Let me fetch the latest data for you."}

  3) Final info:
     Assistant Output: "I'm fine."
     -> {"queue": false, "message": "I'm fine."}

  4) Ask info:
     Assistant Output: "From where do you ...?"
     -> {"queue": false, "message": "From where do you ...?"}
EOT;
    }

    public function function(): Closure
    {
        return function (bool $queue = true, ?string $message = null): mixed {
            return [
                'queue' => $queue !== false || $message === null,
                'message' => $queue ? null : $message,
            ];
        };
    }

    public function temperature(): ?float
    {
        // Less temperature helps to detect if the assistant is in a infinite loop.
        return 0.10;
    }

    public function rules(): array
    {
        return [
            'message' => 'sometimes|string|nullable|max:600',
            'queue' => 'required|boolean',
        ];
    }

    public function parameters(): array
    {
        return [
            'queue' => [
                'type' => 'boolean',
                'description' => 'If true, the assistant will queue the message for further processing.',
            ],
            'message' => [
                'type' => 'string',
                'description' => 'The assistant message to be processed.',
            ],
        ];
    }

    public function requiredParameters(): array
    {
        return [
            'queue',
            'message',
        ];
    }

    public function sendToDecide(): mixed
    {
        $messages = $this->gptChat->messages;

        try {
            return $this->send(
                '<sub-messages>'
                .json_encode($messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                .'</sub-messages>'
            );
        } catch (\Exception $e) {
            return [
                'queue' => true,
                'message' => null,
            ];
        }
    }
}
