<?PHP

namespace druid628\exactTarget;

use druid628\exactTarget\EtBaseClass;
use druid628\exactTarget\EtSubscriberList;

/**
* EtSubscriber (Active Class)
*
* Active Classes accept an instance of druid628\exactTarget\EtClient
* to communicate with the Exact Target server.
*
* @package exactTarget
* @author Micah Breedlove <druid628@gmail.com>
* @version 1.0
*/
class EtSubscriber extends EtBaseClass {

	const ACTIVE = 'Active';
	const BOUNCED = 'Bounced';
	const HELD = 'Held';
	const UNSUBSCRIBED = 'Unsubscribed';
	const DELETED = 'Deleted';

	private $_new = true;
	protected $client;
	public $ID;                        // user ID
	public $IDSpecified;               // user IDSpecified
	public $EmailAddress;              // String
	public $Attributes;                // array() of EtAttribute
	public $SubscriberKey;             // String
	public $UnsubscribedDate;          // dateTime
	public $Status;                    // EtSubscriberStatus
	public $PartnerType;               // String
	public $EmailTypePreference;       // EtEmailType
	public $Lists;                     // EtSubscriberList
	public $GlobalUnsubscribeCategory; // EtGlobalUnsubscribeCategory
	public $SubscriberTypeDefinition;  // EtSubscriberTypeDefinition


	/**
	 * allow for passing optional client class to [some] Et-classes
	 * so they can take advantage of client specific functions.
	 * e.g. send() and save()
	 *
	 * @param druid628\exactTarget\EtClient $EtClient
	 */
	public function __construct($EtClient = null) {
			$this->client = $EtClient;
	}

	/**
	 * Used for setting client after class instantiation
	 *
	 * @param druid628\exactTarget\EtClient $EtClient
	 */
	public function setClient($EtClient) {
			$this->client = $EtClient;
	}

	/**
	 * Get active client instance.
	 *
	 * @return druid628\exactTarget\EtClient
	 */
	public function getClient() {
			return $this->client;
	}

	/**
	 * save() - uses (EtClient) $this->client  to save/update
	 * @throws EtErrorException
	 */
	public function save() {
		 $this->client->updateSubscriber($this);
	}

	/**
	 * find() - find give find at least a subscriberKey (email addy) and it will
	 * recall a subscriber.
	 *
	 * @param string $subscriberKey
	 * @param string $emailAddress
	 * @param array $options - Multidimensional array of other optional data about a subscriber should be in the following co
	 *         array(
	 *             'Name'     => 'SubscriberKey',
	 *             'operator' => 'equals',
	 *             'Value'    => $subscriberKey,
	 *         ),
	 */
	public function find($subscriberKey, $emailAddress = null, $options = array()) {
			$subscriberInfo = array(
				 array(
					  'Name' => 'SubscriberKey',
					  'operator' => 'equals',
					  'Value' => $subscriberKey,
				 ),
			);

			if (!is_null($emailAddress)) {
					$subscriberInfo[] = array(
						 'Name' => 'EmailAddress',
						 'operator' => 'equals',
						 'Value' => $emailAddress,
					);
			}
			if (!empty($options)) {
					$subscriberInfo = array_merge($subscriberInfo, $options);
			}

			if ($newSub = $this->client->recallSubscriber($subscriberInfo)) {
					$this->reAssign($newSub);
					$this->isNotNew();
			} else {
					$this->populateNew($subscriberInfo);
			}
	}

	/**
	 * Used for filling existing object with the data used when locating a subscriber.
	 * @see find() $options array
	 *
	 * @param array $subscriberInfo
	 */
	protected function populateNew($subscriberInfo) {
			foreach ($subscriberInfo as $info) {
					if (strtolower($info['operator']) == "equals") {
							$this->set($info['Name'], $info['Value']);
					}
			}
	}

	/**
	 * getAttribute() - returns an EtAttribute object from ($this) Subscriber
	 *
	 * @param string $attributeName
	 *
	 */
	public function getAttribute($attributeName) {
		$attributes = $this->getAttributes();
		if ( $attributes ) {

			foreach ($attributes as $key => $object) {
				if (strtolower($object->Name) == strtolower($attributeName)) {
						return $this->cast($object, new EtAttribute());
				}
			}

		} // end if
	}

	/**
	 * Takes an EtAttribute removes the existing attribute from the object and sets the new one.
	 * OR
	 * Adds an EtAttribute  to the current subscriber.
	 *
	 * @param druid628\exactTarget\EtAttribute $EtAttribute
	 * @return boolean
	 * @throws \Exception
	 */
	public function updateAttribute($EtAttribute) {
		if (!($EtAttribute instanceof \druid628\exactTarget\EtAttribute)) {
				throw new \Exception(" updateAttribute expects an instance of \druid628\exactTarget\EtAttribute and was given " . get_class($EtAttribute) . ". ");
		}
		$nameToUpdate = $EtAttribute->getName();
		$attributes = $this->getAttributes();

		if ( $attributes ) {
			foreach ($attributes as $key => $object) {
					if (strtolower($object->Name) == strtolower($nameToUpdate)) {
							unset($attributes[$key]);
							continue;
					}
			}
		}
		$attributes[] = $EtAttribute;
		$this->Attributes = array_values($attributes);
		return true;
	}

	/**
	 * addToList
	 *
	 * @author  Joe Sexton <joe.sexton@bigideas.com>
	 * @author  Micah Breedlove <druid628@gmail.com> <micah.breedlove@blueshamrock.com>
	 * @param 	int $listId EtList->getID()
	 * @param 	string $status
	 */
	public function addToList( $listId, $status = null ) {
		$slist = new EtSubscriberList();
		$slist->setID($listId);
		$slist->setAction("create");
		$slist->setStatus($status);
		$list = $this->client->soapCall($slist);
		$this->Lists[] = $list;
	}

	/**
	 * updateListStatus
	 *
	 * @author  Joe Sexton <joe.sexton@bigideas.com>
	 * @param   int $listId EtList->getID()
	 * @param   string $status
	 */
	public function updateListStatus( $listId, $status ) {
		$slist = new EtSubscriberList();
		$slist->setID( $listId );
		$slist->setAction( "Update" );
		$slist->setStatus( $status );
		$list = $this->client->soapCall( $slist );
		$this->Lists[] = $list;
	}

	/**
	 * getLists
	 *
	 * @author 	Joe Sexton <joe.sexton@bigideas.com>
	 * @param 	string $subscriberKey
	 * @return 	Object|null
	 */
	public function getSubscribersLists( $subscriberKey )
	{
		try{
			$rr = new EtRecallRequest();
			$rr->ObjectType = 'ListSubscriber';

			//Set the properties to return
			$props = array( "ListID", "SubscriberKey", "Status" );
			$rr->Properties = $props;

			//Setup account filtering, to look for a given account MID
			$filterPart = new EtSimpleFilterPart();
			$filterPart->Property = 'SubscriberKey';
			$values = array( $subscriberKey );
			$filterPart->Value = $values;
			$filterPart->SimpleOperator = EtSimpleOperators::EQUALS;

			//Encode the SOAP package
			$filterPart = new \SoapVar( $filterPart, SOAP_ENC_OBJECT,'SimpleFilterPart', EtClient::SOAPWSDL );

			$rr->Filter = $filterPart;

			//Setup and execute request
			$rrm = new EtRecallRequestMsg();
			$rrm->RetrieveRequest = $rr;
			$results = $this->client->getClient()->Retrieve( $rrm );

			if ( !empty( $results->Results ) ) {

				return $results->Results;

			} // end if

		} catch ( \SoapFault $e ) {
			throw $e;
		}

		return null;

	} // end getSubscribersLists()


	/**
	 * Sets the object into a not new status
	 *
	 * @return void
	 */
	private function isNotNew() {
			$this->_new = false;
	}

	/**
	 * Determines if an object is new or a retrieved record from ExactTarget
	 *
	 * @return boolean
	 */
	public function isNew() {
			return $this->_new;
	}

}
