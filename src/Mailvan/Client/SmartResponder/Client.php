<?php


namespace Mailvan\Client\SmartResponder;

use Guzzle\Common\Collection;
use Guzzle\Service\Description\ServiceDescription;
use Mailvan\Core\Client as BaseClient;
use Mailvan\Core\MailvanException;
use Mailvan\Core\Model\SubscriptionListInterface;
use Mailvan\Core\Model\UserInterface;

class Client extends BaseClient
{
    public static function factory($config = [])
    {
        $required = ['base_url', 'api_key'];
        $config = Collection::fromConfig($config, [], $required);

        $client = new self($config->get('base_url'), $config);
        $client->setDescription(ServiceDescription::factory(dirname(__FILE__).'/operations.json'));

        return $client;
    }


    /**
     * Merge API key into params array. Some implementations require to do this.
     *
     * @param $params
     * @return mixed
     */
    protected function mergeApiKey($params)
    {
        return array_merge(
            $params,
            ['api_key' => $this->getConfig('api_key')]
        );
    }

    /**
     * Check if server returned response containing error message.
     * This method must return true if servers does return error.
     *
     * @param $response
     * @return mixed
     */
    protected function hasError($response)
    {
        return $response['result'] == 0;
    }

    /**
     * Raises Exception from response data
     *
     * @param $response
     * @return MailvanException
     */
    protected function raiseError($response)
    {
        return new SmartResponderException($response['error']['message'], $response['error']['code']);
    }

    /**
     * Subscribes given user to given SubscriptionList. Returns true if operation is successful
     *
     * @param UserInterface $user
     * @param SubscriptionListInterface $list
     * @return boolean
     */
    public function subscribe(UserInterface $user, SubscriptionListInterface $list)
    {
        return $this->doExecuteCommand(
            'subscribe',
            ['delivery_id' => $list->getId(), 'email' => $user->getEmail(), 'first_name' => $user->getFirstName(), 'last_name' => $user->getLastName()],
            function() {
                return true;
            }
        );
    }

    /**
     * Unsubscribes given user from given SubscriptionList.
     *
     * @param UserInterface $user
     * @param SubscriptionListInterface $list
     * @return boolean
     */
    public function unsubscribe(UserInterface $user, SubscriptionListInterface $list)
    {
        return $this->doExecuteCommand(
            'unsubscribe',
            ['delivery_id' => $list->getId(), 'search' => ['email' => $user->getEmail()]],
            function() {
                return true;
            }
        );
    }

    /**
     * Moves user from one list to another. In some implementation can create several http queries.
     *
     * @param UserInterface $user
     * @param SubscriptionListInterface $from
     * @param SubscriptionListInterface $to
     * @return boolean
     */
    public function move(UserInterface $user, SubscriptionListInterface $from, SubscriptionListInterface $to)
    {
        return $this->unsubscribe($user, $from) && $this->subscribe($user, $to);
    }

    /**
     * Returns list of subscription lists created by owner.
     *
     * @return array
     */
    public function getLists()
    {
        return $this->doExecuteCommand('getLists', [], function($response) {
            return array_map(
                function($item) {
                    return $this->createSubscriptionList($item['id']);
                },
                $response['list']
            );
        });
    }
}