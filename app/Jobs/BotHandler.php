<?php

namespace App\Jobs;

use App\Bot\Webhook\Messaging;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

class BotHandler implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    //the messaging instance sent to our bothandler
    protected $messaging;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Messaging $messaging)
    {
        $this->messaging = $messaging;
    }

    /**
     * Execute the job.
     *
     * @param Messaging $messaging
     */
    public function handle()
    {
        if ($this->messaging->getType() == "message")   {
            $bot = new Bot($this->messaging);
            $custom = $bot->extractDataFromMessage();
            // a request for a new question
            if ($custom["type"] == Trivia::$NEW_QUESTION)   {
                $bot->reply(Trivia::getNew($custom['user_id']));
            }   else if ($custom["type"] == Trivia::$ANSWER)    {
                $bot->reply(Trivia::checkAnswer($custom["data"]["answer"], $custom['user_id']));
            }   else    {
                $bot->reply("I don't understand. Try \"new\" for a new question");
            }
        }
    }

    public function extractDataFromMessage()    {
        $matches = [];
        $text = $this->messaging->getMessage()->getText();
        //single letter means an answer
        if (preg_match("/^(\\w)\$/i", $text, $matches)) {
            return [
                "type" => Trivia::ANSWER,
                "data" => [
                    "answer" => $matches[0]
                ],
                "user_id" => $this->messaging->getSenderId()
            ];
        }
        else if (preg_match("/^new|next\$/i", $text, $matches)) {
            // "new" or "next" requires a new question
            return [
                "type" => Trivia::NEW_QUESTION,
                "data" => [],
                "user_id" => $this->messaging->getSenderId()
            ];
        }
        // anything else, we don't care
        return [
            "type" => "unknown",
            "data" => [],
            "user_id" => $this->messaging->getSenderId()
        ];
    }

    public function reply($data) {
        if (method_exists($data, "toMessengerMessage")) {
            $data = $data->toMessengerMessage();
        }   elseif (gettype($data) == "string") {
            $data = ["text" => $data];
        }
        $id = $this->messaging->getSenderId();
        $this->sendMessage($id, $data);
    }

    private function sendMessage($recipientId, $message)    {
        $messageData = [
            "recipient" => [
                "id" => $recipientId
            ],
            "message" => $message
        ];

        $ch = curl_init('https://graph/facebook.com/v2.6/me/messages?access_token=' . env("PAGE_ACCESS_TOKEN"));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($messageData));
        Log::info(print_r(curl_exec($ch), true));
    }
}
