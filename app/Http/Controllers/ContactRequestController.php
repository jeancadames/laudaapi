<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactRequest;
use App\Mail\ContactConfirmationMail;
use App\Mail\ContactInternalNotificationMail;
use App\Models\ContactRequest;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ContactRequestController extends Controller
{
    public function store(StoreContactRequest $request)
    {
        try {
            /** @var ContactRequest $contact */
            $contact = DB::transaction(function () use ($request) {
                $data = $request->validated();

                // Asegura boolean real
                $data['terms'] = $request->boolean('terms');

                $contact = ContactRequest::create($data);

                AuditService::log('contact_created', $contact, [
                    'email' => $contact->email,
                    'name'  => $contact->name,
                    'company' => $contact->company,
                ]);

                return $contact;
            });
        } catch (\Throwable $e) {
            Log::error('Error creando contact request: ' . $e->getMessage(), [
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al procesar la solicitud.',
            ], 500);
        }

        // Correos fuera de transacción (DB ya consistente)
        try {
            Mail::to('contacto@laudaapi.com')
                ->queue(new ContactInternalNotificationMail($contact));

            Mail::to($contact->email)
                ->queue(new ContactConfirmationMail($contact));
        } catch (\Throwable $e) {
            // No rompemos la respuesta por fallo de correo
            Log::warning('ContactRequest creada pero falló envío de correos: ' . $e->getMessage(), [
                'contact_request_id' => $contact->id,
                'exception' => $e,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Formulario enviado correctamente.',
        ]);
    }
}
