<?php

namespace Lenorix\LaravelAiJobs\Actions\ReplyOrQueueAiJob;

use Closure;
use MalteKuhr\LaravelGPT\Extensions\FillableGPTChat;
use MalteKuhr\LaravelGPT\Extensions\FillableGPTFunction;
use MalteKuhr\LaravelGPT\Generators\ChatPayloadGenerator;
use MalteKuhr\LaravelGPT\GPTAction;
use MalteKuhr\LaravelGPT\GPTChat;

class ReplyOrQueueAiJobGPTAction extends GPTAction
{
    public function __construct(
        protected GPTChat $gptChat,
    ) {}

    public function systemMessage(): ?string
    {
        $systemPrompt = $this->gptChat->systemMessage() ?? '';
        $messages = $this->gptChat->messages;
        $functions = ChatPayloadGenerator::make($this->gptChat)->generate()['functions'] ?? [];

        return <<<'EOT'
You are a Fast/Slow Decision Controller. Given:

  1) The original system prompt and available functions/tools.
  2) The full message history.

Decide if the next assistant output requires tool execution or deeper reasoning (`queue` = true)
or is a simple reply (`queue` = false).

Rules:
  • If `queue` = false, `message` is the exact assistant reply.
  • If `queue` = true, `message` MUST be null.
  • Do not emit any text outside this JSON.
  • Reply `message` can not tell it will do an action or call a function/tool, only can be a simple reply.
  • Reply `message`must be short, less than 500 characters, if more, use queue=true.

Queue=true when:
  - User or assistant is calling/executing tools (search, fetch, call API, run, query).
  - The reply is incomplete, a placeholder, an internal thought or plan.

Queue=false when:
  - Purely informational, final answer, greeting, simple confirmation or direct question to user.

Examples:

  1) Greeting:
     Last Input: "Hello!"
     Your Output: "Hi there!"
     -> {"queue": false, "message": "Hi there!"}

  2) Tool request or internal reasoning:
     Your Output: "Let me fetch the latest data."
     -> {"queue": true, "message": null}

  3) Final info:
     Your Output: "Here’s the report."
     -> {"queue": false, "message": "Here’s the report."}

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

Messages:

<sub-messages>
EOT
        .json_encode($messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        .<<<'EOT'
</sub-messages>

Resumen:
- Usa prompt original, herramientas y mensajes como contexto.
- queue=true para acciones o razonamiento complejo (message=null).
- queue=false para respuestas simples (message limit is 500 caracteres).
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
        return 0.15;
    }

    public function rules(): array
    {
        return [
            'queue' => 'required|boolean',
            'message' => 'sometimes|string|nullable|max:500',
        ];
    }

    public function sendToDecide(): mixed
    {
        $this->chat = FillableGPTChat::make(
            systemMessage: fn () => $this->systemMessage(),
            functions: fn () => [
                new FillableGPTFunction(
                    name: fn () => $this->functionName(),
                    description: fn () => $this->description(),
                    function: fn () => $this->function(),
                    rules: fn () => $this->rules(),
                ),
            ],
            functionCall: fn () => FillableGPTFunction::class,
            model: fn () => $this->model(),
            temperature: fn () => $this->temperature(),
            maxTokens: fn () => $this->maxTokens(),
            sending: fn () => $this->sending(),
            received: fn () => $this->received(),
        );

        $this->chat->send();

        return $this->chat->latestMessage()->content;
    }
}
