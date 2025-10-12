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
use Illuminate\Support\Facades\Log;

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
        try {
            Log::info('ImportAttendeesAction called', ['request_data' => $request->all()]);

            // Validate request - match frontend field name 'file' instead of 'csv_file'
            $request->validate([
                'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:10240',
                'event_id' => 'required|exists:events,id'
            ]);

            // Debug file upload
            if (!$request->hasFile('file')) {
                throw new \Exception('No file uploaded');
            }

            $file = $request->file('file');
            $eventId = (int) $request->event_id;
            
            Log::info('File received for import', [
                'event_id' => $eventId,
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'file_type' => $file->getMimeType()
            ]);

            $imported = 0;
            $errors = [];
            $rowNumber = 0;
            
            if (($handle = fopen($file->getPathname(), 'r')) !== FALSE) {
                $headers = fgetcsv($handle); // Skip header row
                Log::info('CSV headers', ['headers' => $headers]);
                
                while (($data = fgetcsv($handle)) !== FALSE) {
                    $rowNumber++;
                    try {
                        DB::transaction(function () use ($data, $eventId, &$imported, &$errors, $rowNumber) {
                            if (count($data) < 3) {
                                $errors[] = "Row {$rowNumber}: Insufficient data (need at least first name, last name, email)";
                                return;
                            }

                            $firstName = trim($data[0] ?? '');
                            $lastName = trim($data[1] ?? '');
                            $email = trim($data[2] ?? '');
                            $organization = isset($data[3]) ? trim($data[3]) : null;
                            $ticketType = isset($data[4]) ? trim($data[4]) : 'Standard';
                            $amountPaid = isset($data[5]) ? (float) $data[5] : 0;

                            // Validate required fields
                            if (empty($firstName) || empty($lastName) || empty($email)) {
                                $errors[] = "Row {$rowNumber}: Missing required fields (first name, last name, or email)";
                                return;
                            }

                            // Validate email format
                            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                                $errors[] = "Row {$rowNumber}: Invalid email format '{$email}'";
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

                            Log::info("Successfully imported attendee", [
                                'row' => $rowNumber,
                                'email' => $email,
                                'attendee_id' => $attendee->getId()
                            ]);
                        });
                    } catch (\Exception $e) {
                        Log::error("Row {$rowNumber} import failed", [
                            'error' => $e->getMessage(),
                            'data' => $data,
                            'trace' => $e->getTraceAsString()
                        ]);
                        $errors[] = "Row {$rowNumber}: " . $e->getMessage();
                    }
                }
                fclose($handle);
            } else {
                throw new \Exception('Could not open uploaded file for reading');
            }
            
            Log::info('Import completed', [
                'event_id' => $eventId,
                'imported' => $imported,
                'errors' => count($errors),
                'total_rows_processed' => $rowNumber
            ]);
            
            return new JsonResponse([
                'success' => true,
                'message' => "Successfully imported {$imported} attendees" . (count($errors) ? " with " . count($errors) . " errors" : ""),
                'imported' => $imported,
                'errors' => $errors,
                'total_rows' => $rowNumber
            ]);
            
        } catch (\Exception $e) {
            Log::error('ImportAttendeesAction failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);
            
            return new JsonResponse([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage(),
                'imported' => 0,
                'errors' => [$e->getMessage()]
            ], 500);
        }
    }

    private function getOrCreateProduct(int $eventId, string $ticketType, float $amountPaid): ProductDomainObject
    {
        try {
            // Try to find existing product with same title
            $existingProduct = $this->productRepository->findFirstBy([
                'event_id' => $eventId,
                'title' => $ticketType
            ]);

            if ($existingProduct) {
                Log::debug('Found existing product', [
                    'product_id' => $existingProduct->getId(),
                    'title' => $ticketType
                ]);
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
            
            Log::info('Created new product for import', [
                'product_id' => $product->getId(),
                'title' => $ticketType,
                'price' => $amountPaid
            ]);
            
            return $product;
            
        } catch (\Exception $e) {
            Log::error('Failed to get or create product', [
                'event_id' => $eventId,
                'ticket_type' => $ticketType,
                'error' => $e->getMessage()
            ]);
            throw new \Exception("Failed to create product '{$ticketType}': " . $e->getMessage());
        }
    }

    private function createOrderForAttendee(int $eventId, string $email, float $amountPaid): OrderDomainObject
    {
        try {
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
            
            Log::debug('Created order for imported attendee', [
                'order_id' => $order->getId(),
                'email' => $email,
                'amount' => $amountPaid
            ]);
            
            return $order;
            
        } catch (\Exception $e) {
            Log::error('Failed to create order for attendee', [
                'event_id' => $eventId,
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            throw new \Exception("Failed to create order for {$email}: " . $e->getMessage());
        }
    }
}
