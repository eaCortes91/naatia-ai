<?php

namespace Database\Seeders;

use App\Models\Faq;
use App\Models\Hotel;
use App\Models\Rate;
use App\Models\HotelPackage;
use App\Models\HotelService;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Database\Seeder;

class HotelDemoSeeder extends Seeder
{
    public function run(): void
    {
        $hotel = Hotel::query()->firstOrCreate(
            ['email' => 'demo@hotel.com'],
            [
                'nombre' => 'Hotel Demo',
                'telefono' => '7770000000',
                'prompt_base' => 'Eres la recepcionista virtual de un hotel boutique. Responde de forma cálida, breve y orientada a reservaciones.',
                'saludo_base' => 'Hola, gracias por escribirnos. Con gusto te ayudo con información sobre habitaciones, tarifas y hospedaje.',
                'activo' => true,
            ]
        );

        $hotel->update([
            'address_line' => 'Avenida Revolución 1910 número 18',
            'neighborhood' => 'Barrio San José',
            'city' => 'Tepoztlán',
            'state' => 'Morelos',
            'postal_code' => '62520',
            'latitude' => 18.98310519,
            'longitude' => -99.09054278,
            'check_in_time' => '3:00 PM',
            'check_out_time' => '12:00 PM',
            'pet_friendly' => false,
            'amenities_text' => 'Alberca climatizada a 24°C hasta las 6:00 PM, spa, temazcal, restaurante, jardines, salón de yoga.',
            'policies_text' => 'No pet friendly.',
        ]);

        $tipoEstandar = RoomType::query()->firstOrCreate(
            ['hotel_id' => $hotel->id, 'name' => 'Estándar'],
            ['color' => '#2563eb', 'active' => true]
        );

        $tipoDiamante = RoomType::query()->firstOrCreate(
            ['hotel_id' => $hotel->id, 'name' => 'Diamante'],
            ['color' => '#7c3aed', 'active' => true]
        );

        $habitacionSencilla = Room::query()->firstOrCreate(
            [
                'hotel_id' => $hotel->id,
                'nombre' => 'Sencilla',
            ],
            [
                'room_type_id' => $tipoEstandar->id,
                'descripcion' => 'Habitación compacta para viaje corto.',
                'capacidad' => 2,
                'inventario_total' => 5,
                'weekday_rate' => 950,
                'weekend_rate' => 1200,
                'base_status' => 'libre',
                'activo' => true,
            ]
        );

        $habitacionEstandar = Room::query()->firstOrCreate(
            [
                'hotel_id' => $hotel->id,
                'nombre' => 'Estándar',
            ],
            [
                'room_type_id' => $tipoEstandar->id,
                'descripcion' => 'Habitación cómoda con cama matrimonial.',
                'capacidad' => 2,
                'inventario_total' => 3,
                'weekday_rate' => 1200,
                'weekend_rate' => 1500,
                'base_status' => 'libre',
                'activo' => true,
            ]
        );

        $habitacionSuite = Room::query()->firstOrCreate(
            [
                'hotel_id' => $hotel->id,
                'nombre' => 'Suite',
            ],
            [
                'room_type_id' => $tipoDiamante->id,
                'descripcion' => 'Habitación amplia con cama king y sofá cama.',
                'capacidad' => 4,
                'inventario_total' => 1,
                'weekday_rate' => 1800,
                'weekend_rate' => 2200,
                'base_status' => 'libre',
                'activo' => true,
            ]
        );

        $habitacionSencilla->update(['inventario_total' => 5]);
        $habitacionEstandar->update(['inventario_total' => 3]);
        $habitacionSuite->update(['inventario_total' => 1]);

        $rates = [
            ['room' => $habitacionSencilla, 'tipo_dia' => 'entre semana', 'precio' => 950],
            ['room' => $habitacionSencilla, 'tipo_dia' => 'fin de semana', 'precio' => 1200],
            ['room' => $habitacionEstandar, 'tipo_dia' => 'entre semana', 'precio' => 1200],
            ['room' => $habitacionEstandar, 'tipo_dia' => 'fin de semana', 'precio' => 1500],
            ['room' => $habitacionSuite, 'tipo_dia' => 'entre semana', 'precio' => 1800],
            ['room' => $habitacionSuite, 'tipo_dia' => 'fin de semana', 'precio' => 2200],
        ];

        foreach ($rates as $rateData) {
            Rate::query()->firstOrCreate(
                [
                    'hotel_id' => $hotel->id,
                    'room_id' => $rateData['room']->id,
                    'tipo_dia' => $rateData['tipo_dia'],
                ],
                [
                    'precio' => $rateData['precio'],
                ]
            );
        }

        $faqs = [
            ['pregunta' => '¿Incluye desayuno?', 'respuesta' => 'Sí, desayuno continental incluido.'],
            ['pregunta' => '¿Hora de check-in?', 'respuesta' => 'El check-in es a las 3:00 PM.'],
            ['pregunta' => '¿Hora de check-out?', 'respuesta' => 'El check-out es a las 12:00 PM.'],
            ['pregunta' => '¿Son pet friendly?', 'respuesta' => 'No, por ahora no somos pet friendly.'],
            ['pregunta' => '¿Qué amenidades tienen?', 'respuesta' => 'Contamos con alberca climatizada a 24°C (hasta las 6:00 PM), spa, temazcal, restaurante, jardines y salón de yoga.'],
            ['pregunta' => '¿Cuál es la dirección del hotel?', 'respuesta' => 'Estamos en Avenida Revolución 1910 número 18, Barrio San José, Tepoztlán, Morelos, CP 62520.'],
        ];

        foreach ($faqs as $faqData) {
            Faq::query()->firstOrCreate(
                [
                    'hotel_id' => $hotel->id,
                    'pregunta' => $faqData['pregunta'],
                ],
                [
                    'respuesta' => $faqData['respuesta'],
                    'activo' => true,
                ]
            );
        }

        $services = [
            ['name' => 'Masaje relajante', 'description' => 'Sesión individual de 60 minutos.', 'price' => 900],
            ['name' => 'Temazcal', 'description' => 'Experiencia guiada tradicional.', 'price' => 1200],
            ['name' => 'Masaje a dos manos', 'description' => 'Terapia profunda con dos terapeutas.', 'price' => 1500],
            ['name' => 'Clase de yoga', 'description' => 'Sesión matutina para huéspedes.', 'price' => 500],
        ];

        foreach ($services as $serviceData) {
            HotelService::query()->firstOrCreate(
                ['hotel_id' => $hotel->id, 'name' => $serviceData['name']],
                [
                    'description' => $serviceData['description'],
                    'price' => $serviceData['price'],
                    'active' => true,
                ]
            );
        }

        HotelPackage::query()->firstOrCreate(
            ['hotel_id' => $hotel->id, 'name' => 'Escapada Wellness'],
            [
                'description' => 'Incluye temazcal + masaje relajante con tarifa preferencial.',
                'price' => 1800,
                'color' => '#1fb7b2',
                'active' => true,
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'admin@bot-hotel.local'],
            [
                'hotel_id' => $hotel->id,
                'name' => 'Admin NAATIA',
                'password' => 'admin12345',
            ]
        );
    }
}
