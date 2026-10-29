<?php

namespace WPIDE\App\Services\Snippets\Generators;

use WPIDE\App\Services\Snippets\Generators\Support\SchemaGenerator;

/**
 * Car (vehicle listing) structured data.
 */
class SchemaCar extends SchemaGenerator
{
    public function id(): string
    {
        return 'schema-car';
    }

    public function info(): array
    {
        return [
            'title' => __('Car Schema', 'wpide'),
            'description' => __('Describe a vehicle for sale: make, model, year, mileage, VIN, price and dealer.', 'wpide'),
            'category' => 'schema',
            'tags' => ['schema', 'car', 'vehicle', 'dealer', 'json-ld'],
        ];
    }

    public function fields(): array
    {
        return [
            'vehicle' => [
                'label' => __('Vehicle', 'wpide'),
                'fields' => [
                    $this->text('vehicle_name', __('Vehicle name', 'wpide'), '{title}', '2023 Toyota Camry SE'),
                    $this->textarea('vehicle_description', __('Description', 'wpide'), '{excerpt}'),
                    $this->text('vehicle_url', __('URL', 'wpide'), '{permalink}'),
                    $this->text('vehicle_image', __('Image URL', 'wpide'), '{featured_image}'),
                    $this->text('vehicle_brand', __('Make (brand)', 'wpide'), '', 'Toyota'),
                    $this->text('vehicle_model', __('Model', 'wpide'), '', 'Camry'),
                    $this->text('vehicle_model_year', __('Model year', 'wpide'), '', '2023'),
                    $this->text('vehicle_vin', __('VIN', 'wpide'), '', '1HGCM82633A123456', __('Vehicle identification number.', 'wpide')),
                    $this->text('vehicle_fuel_type', __('Fuel type', 'wpide'), '', 'Gasoline'),
                    $this->text('vehicle_color', __('Color', 'wpide'), '', 'Silver'),
                    $this->text('vehicle_mileage', __('Mileage', 'wpide'), '', '25000'),
                    $this->select('vehicle_mileage_unit', __('Mileage unit', 'wpide'), ['KMT' => __('Kilometers', 'wpide'), 'SMI' => __('Miles', 'wpide')], 'KMT'),
                    $this->select('vehicle_condition', __('Condition', 'wpide'), ['' => __('Not specified', 'wpide'), 'NewCondition' => __('New', 'wpide'), 'UsedCondition' => __('Used', 'wpide'), 'RefurbishedCondition' => __('Refurbished', 'wpide')], ''),
                ],
            ],
            'offer' => [
                'label' => __('Offer', 'wpide'),
                'fields' => [
                    $this->text('offer_price', __('Price', 'wpide'), '', '25000'),
                    $this->currencyField('offer_price_currency'),
                    $this->availabilityField('offer_availability'),
                    $this->text('seller_name', __('Seller / dealer name', 'wpide'), '{site_name}'),
                    $this->text('seller_url', __('Seller URL', 'wpide'), '{site_url}'),
                ],
            ],
        ];
    }

    public function generate(array $values): string
    {
        $offer = [];

        if ($values['offer_price'] !== '') {
            $offer = [
                '@type' => 'Offer',
                'price' => $this->price($values['offer_price']),
                'priceCurrency' => $values['offer_price_currency'],
                'availability' => $this->enum($values['offer_availability']),
                'itemCondition' => $this->enum($values['vehicle_condition']),
                'url' => $values['vehicle_url'],
                'seller' => $this->node('AutoDealer', $values['seller_name'], ['url' => $values['seller_url']]),
            ];
        }

        return $this->jsonLd([
            '@type' => 'Car',
            'name' => $values['vehicle_name'],
            'description' => $values['vehicle_description'],
            'url' => $values['vehicle_url'],
            'image' => $values['vehicle_image'],
            'brand' => $this->node('Brand', $values['vehicle_brand']),
            'model' => $values['vehicle_model'],
            'vehicleModelDate' => $values['vehicle_model_year'],
            'vehicleIdentificationNumber' => $values['vehicle_vin'],
            'fuelType' => $values['vehicle_fuel_type'],
            'color' => $values['vehicle_color'],
            'itemCondition' => $this->enum($values['vehicle_condition']),
            'mileageFromOdometer' => $values['vehicle_mileage'] !== '' ? ['@type' => 'QuantitativeValue', 'value' => $this->number(str_replace(',', '', $values['vehicle_mileage'])), 'unitCode' => $values['vehicle_mileage_unit']] : [],
            'offers' => $offer,
        ]);
    }
}
