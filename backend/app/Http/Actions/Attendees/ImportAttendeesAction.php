<?php

namespace HiEvents\Http\Actions\Attendees;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Services\Handlers\Order\CreateOrderHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportAttendeesAction extends BaseAction
{
    public function __construct(
        private readonly AttendeeRepositoryInterface $attendeeRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly CreateOrderHandler $createOrderHandler,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:10240',
            'event_id' => 'required|exists:events,id'
        ]);

        $file = $request->file('csv_file');
        $eventId = (int) $request->event_id;
        
        $imported = 0;
        $errors = [];
        
        if (($handle = fopen($file->getPathname(), 'r')) !== FALSE) {
            $headers = fgetcsv($handle); // Skip header row
            
            while (($data = fgetcsv($handle)) !== FALSE) {
                try {
                    DB::transaction(function () use ($data, $eventId, &$imported, &$errors) {
                        if (count($data) < 3) {
                            $errors[] = "Row " . ($imported + 1) . ": Insufficient data";
                            return;
                        }

                        $firstName = $data[0] ?? '';
                        $lastName = $data[1] ?? '';
                        $email = $data[2] ?? '';
                        $organization = $data[3] ?? null;
                        $ticketType = $data[4] ?? 'Standard';
                        $amountPaid = isset($data[5]) ? (float) $data[5] : 0;

                        // Validate required fields
                        if (empty($firstName) || empty($lastName) || empty($email)) {
                            $errors[] = "Row " . ($imported + 1) . ": Missing required fields (first name, last name, or email)";
                            return;
                        }

                        // Find or create a product for the ticket type
                        $product = $this->getOrCreateProduct($eventId, $ticketType, $amountPaid);

                        // Create an order for the attendee
                        $order = $this->createOrderForAttendee($eventId, $email, $amountPaid);

                        // Create the attendee
                        $attendee = new AttendeeDomainObject();
                        $attendee->setEventId($eventId)
                            ->setOrderId($order->getId())
                            ->setProductId($product->getId())
                            ->setFirstName($firstName)
                            ->setLastName($lastName)
                            ->setEmail($email)
                            ->setOrganization($organization)
                            ->setStatus('ACTIVE')
                            ->setShortId(Str::random(8))
                            ->setPublicId(Str::uuid()->toString())
                            ->setLocale('en');

                        $this->attendeeRepository->save($attendee);
                        $imported++;
                    });
                } catch (\Exception $e) {
                    $errors[] = "Row " . ($imported + 1) . ": " . $e->getMessage();
                }
            }
            fclose($handle);
        }
        
        return new JsonResponse([
            'success' => true,
            'imported' => $imported,
            'errors' => $errors
        ]);
    }

    private function getOrCreateProduct(int $eventId, string $ticketType, float $amountPaid): ProductDomainObject
    {
        // Try to find existing product with same title
        $existingProduct = $this->productRepository->findFirstBy([
            'event_id' => $eventId,
            'title' => $ticketType
        ]);

        if ($existingProduct) {
            return $existingProduct;
        }

        // Create new product
        $product = new ProductDomainObject();
        $product->setEventId($eventId)
            ->setTitle($ticketType)
            ->setDescription("Imported ticket: " . $ticketType)
            ->setStatus('ACTIVE')
            ->setQuantityAvailable(1000) // High limit for imported tickets
            ->setPrice($amountPaid * 100) // Convert to cents
            ->setSortOrder(999);

        $this->productRepository->save($product);
        return $product;
    }

    private function createOrderForAttendee(int $eventId, string $email, float $amountPaid): OrderDomainObject
    {
        // Create a simple order for the imported attendee
        $order = new OrderDomainObject();
        $order->setEventId($eventId)
            ->setEmail($email)
            ->setStatus('COMPLETED')
            ->setTotalAmount($amountPaid * 100) // Convert to cents
            ->setCurrency('KES')
            ->setPublicId(Str::uuid()->toString())
            ->setShortId(Str::random(8));

        $this->orderRepository->save($order);
        return $order;
    }
}
