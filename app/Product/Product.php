<?php

namespace App\Product;

use Illuminate\Support\Collection;

class Product extends \App\BaseModel
{
    protected $appends = ['productionType'];

    /**
     * Validation rules.
     *
     * @var array
     */
    public $validationRules = [
        'producer_id' => 'required|integer',
        'name' => 'required',
        'info' => 'required',
        'price_unit' => 'required',
        'price' => 'required',
        'package_amount' => '',
        'is_hidden' => '',
        'deadline' => 'integer',
        'payment_info' => ''
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'producer_id',
        'name',
        'info',
        'price_unit',
        'price',
        'package_amount',
        'is_hidden',
        'deadline',
        'payment_info'
    ];

    /**
     * Lifecycle events.
     */
    protected static function boot() {
        parent::boot();

        static::deleting(function($product) {
            $product->variants()->each->delete();
            $product->productions()->each->delete();
            $product->deliveryLinks()->each->delete();
            $product->tags()->each->delete();
            $product->permalink()->delete();
        });

        static::saving(function($product) {
            $product->package_amount = (float) str_replace(',', '.', $product->package_amount);
        });

        static::created(function($product) {
            \App\Permalink::create(['entity_type' => 'product', 'entity_id' => $product->id, 'slug' => $product->name]);
        });

        static::updated(function($product) {
            $permalink = $product->permalink();
            $permalink->slug = $product->name;
            $permalink->save();
        });
    }

    /**
     * Get productions.
     */
    public function producer()
    {
        return $this->belongsTo('App\Producer\Producer')->first();
    }

    /**
     * Define product relationship with productions.
     */
    public function productionsRelationship()
    {
        return $this->hasMany('App\Product\ProductProduction');
    }

    /**
     * Get productions.
     */
    public function productions()
    {
        return $this->productionsRelationship->sortBy('date');
    }

    /**
     * Define product relationship with production adjustments.
     */
    public function productionAdjustments()
    {
        return $this->hasMany('App\Product\ProductProductionAdjustment')->get();
    }

    /**
     * Get production adjustment.
     *
     * @param int $year
     * @param int $week
     * @return ProductProductionAdjustment
     */
    public function productionAdjustment($year, $week)
    {
        return $this->productionAdjustments()->where('year', $year)->where('week', $week)->first();
    }

    /**
     * Get product adjustment quantity.
     *
     * @param int $year
     * @param int $week
     * @return int|null
     */
    public function productionAdjustmentQuantity($year, $week)
    {
        $productionAdjustment = $this->productionAdjustment($year, $week);
        if ($productionAdjustment) {
            return $productionAdjustment->quantity;
        } else {
            return null;
        }
    }

    /**
     * Define relationship with variants.
     */
    public function productVariantsRelationship()
    {
        return $this->hasMany('App\Product\ProductVariant');
    }

    /**
     * Get product variants.
     */
    public function productVariants()
    {
        return $this->productVariantsRelationship()->orderBy('price')->get();
    }

    /**
     * Get variants.
     *
     * @return Collection
     */
    public function variants($includeMainVariant = true)
    {
        return $this->productVariants();
    }

    /**
     * Get specific variant.
     *
     * @param int $variantId
     * @return ProductVariant
     */
    public function variant($variantId)
    {
        return $this->productVariants()->where('id', $variantId)->first();
    }

    /**
     * Get products main variant.
     *
     * @return ProductVariant
     */
    public function mainVariant()
    {
        $mainVariant = $this->productVariants()->where('main_variant', 1)->first();

        // Temp fix to avoid crash if main variant flag is missing.
        if (!$mainVariant) {
            $mainVariant = $this->productVariants()->first();
        }

        return $mainVariant;
    }

    /**
     * Get smallest variant.
     *
     * @return ProductVariant
     */
    public function smallestVariant()
    {
        return $this->productVariants()->sortBy('price')->first();
    }

    /**
     * Return all variant names as concat string.
     *
     * @return string
     */
    public function variantsAsString()
    {
        return $this->variants()->pluck('name')->implode(', ');
    }

    /**
     * Return all variant names as concat string.
     *
     * @return string
     */
    public function variantCount()
    {
        return $this->variants->count();
    }

    /**
     * Define product relationship with node delivery links.
     */
    public function deliveryLinksRelationship()
    {
        return $this->hasMany('App\Product\ProductNodeDeliveryLink');
    }

    /**
     * Get delivery links.
     *
     * @param int $nodeId
     * @param array $dates
     * @return Collection
     */
    public function deliveryLinks($nodeId = null, Collection $dates = null, $ignoreDeadline = false)
    {
        $deliveryLinks = $this->deliveryLinksRelationship;

        if ($nodeId) {
            $deliveryLinks = $deliveryLinks->where('node_id', $nodeId);
        }

        if (!empty($dates)) {
            $deliveryLinks = $deliveryLinks->filter(function($item, $key) use ($dates) {
                return $dates->contains($item->date('Y-m-d'));
            });
        }

        // Adjust for product booking deadline
        if ($ignoreDeadline !== true) {
            if ($this->deadline) {
                $currenctDate = new \DateTime(date('Y-m-d'));
                $deadlineModifyString = '+' . $this->deadline . ' days';
                $firstBookableDate = $currenctDate->modify($deadlineModifyString);
            } else {
                $firstBookableDate = new \DateTime(date('Y-m-d'));
            }

            $deliveryLinks = $deliveryLinks->where('date', '>=', $firstBookableDate);
        }

        return $deliveryLinks->sortBy('date');
    }

    /**
     * Get specific delivery link.
     *
     * @param int $nodeId
     * @param string $date
     * @return ProductNodeDeliveryLink
     */
    public function deliveryLink($nodeId, $date, $ignoreDeadline = false)
    {
        $dates = new Collection([$date]);

        return $this->deliveryLinks($nodeId, $dates, $ignoreDeadline)->first();
    }

    /**
     * Get delivery links by months.
     *
     * @return Collection
     */
    public function getDeliveryLinksByMonths($nodeId)
    {
        return $this->deliveryLinks($nodeId)->groupBy(function($deliveryLink) {
            return (int) date('Ym01', $deliveryLink->date->getTimestamp());
        });
    }

    /**
     * Get tags.
     */
    public function tags()
    {
        return $this->hasMany('App\Product\ProductTag')->get();
    }

    /**
     * Get specific tag.
     */
    public function tag($tag)
    {
        return $this->tags()->where('tag', $tag)->first();
    }

    /**
     * Get permalink.
     */
    public function permalink()
    {
        return $this->hasOne('App\Permalink', 'entity_id')->where('entity_type', 'product')->first();
    }

    /**
     * Define relationship with images.
     */
    public function imageRelationship()
    {
        return $this->hasMany('App\Image\Image', 'entity_id')->where('entity_type', 'product');
    }

    /**
     * Get images.
     */
    public function images()
    {
        return $this->imageRelationship()->get()->sortBy('sort');
    }

    /**
     * Get specific image.
     *
     * @param int $imageId
     * @return Image
     */
    public function image($imageId)
    {
        return $this->images()->where('id', $imageId)->first();
    }

    /**
     * Return all variant names as concat string.
     *
     * @return string
     */
    public function priceRange($minOrMax)
    {
        if ($minOrMax === 'min') {
            return $this->variants()->min('price');
        } else if ($minOrMax === 'max') {
            return $this->variants()->max('price');
        }
    }

    /**
     * Get package amount unit.
     *
     * @return string
     */
    public function getPackageAmountUnit()
    {
        if ($this->price_unit !== 'product' && $this->package_amount) {
            return '(' . $this->package_amount . ' ' . trans_choice('units.' . $this->price_unit, $this->package_amount) . ')';
        }
    }

    /**
     * Get product unit.
     *
     * @return string
     */
    public function getUnit()
    {
        if (\UnitsHelper::isStandardUnit($this->price_unit)) {
            return $this->producer()->currency . '/' . $this->price_unit;
        } else {
            return $this->producer()->currency;
        }
    }

    /**
     * Get price with unit.
     *
     * @return string
     */
    public function getPriceWithUnit()
    {
        return $this->price . ' ' . $this->getUnit();
    }

    /**
     * Get price and unit html.
     *
     * @return string
     */
    public function getPriceWithUnitHtml()
    {
        $prefix = '';
        if (\UnitsHelper::isStandardUnit($this->price_unit)) {
            $prefix = '<span class="approx">&asymp;</span>';
        }

        return '<h3 class="price">' . $prefix . $this->price . '</h3><div class="unit">' . $this->getUnit() . '</div>';
    }

    /**
     * Get production type based on the productions defined for this product.
     *
     * @return string
     */
    public function getProductionTypeAttribute()
    {
        $type = null;

        if ($this->productions() && $this->productions()->count() > 0) {
            $type = $this->productions()->first()->type;
        }

        return $type;
    }

    /**
     * [getProductionQuantity description]
     * @param  [type] $date [description]
     * @return [type]       [description]
     */
    public function getProductionQuantity(\DateTime $date = null, $cartQuantity = null)
    {
        $quantity = 0;

        if ($this->productionType === 'csa') {
            $quantity = $this->productions()->first()->quantity;
        } else if ($this->productionType === 'weekly') {
            $quantity = $this->productions()->first()->quantity;

            if ($date) {
                $productionAdjustment = $this->productionAdjustment($date->format('Y'), $date->format('W'));
                if ($productionAdjustment) {
                    $quantity += $productionAdjustment->quantity;
                }
            }
        } else if ($this->productionType === 'occasional') {
            $occasionalQuantity = 0;

            // Sum production quantities up to the delivery date
            $this->productions()->each(function($production) use (&$occasionalQuantity, $date) {
                if (!$date) {
                    $date = new \DateTime();
                }

                if ($production->date->getTimestamp() <= $date->getTimestamp()) {
                    $occasionalQuantity += $production->quantity;
                }
            });

            $quantity = $occasionalQuantity;

            if ($cartQuantity) {
                $quantity -= $cartQuantity;
            }
        }

        return $quantity;
    }

    /**
     * Check if product has all the needed data to be visible.
     *
     * @return boolean
     */
    public function isVisible($nodeId = null)
    {
        $isVisible = true;
        $errors = new Collection();

        if ($this->is_hidden === 1) {
            $isVisible = false;
            $errors->push('Product is hidden.');
        }

        if ($this->productions()->count() <= 0) {
            $isVisible = false;
            $errors->push('Product has no quantity.');
        }

        if ($this->deliveryLinks($nodeId, null, true)->count() <= 0) { // method params are (node, dates, ignore deadline)
            $isVisible = false;
            $errors->push('Product has no delivery dates.');
        }

        if ($isVisible) {
            return true;
        } else {
            return $errors->implode(' ');
        }
    }

    /**
     * Get the date that for the product deadline. After this dates products no longer bookable.
     *
     * @return DateTime
     */
    public function getDeadlineDate()
    {
        $dateTime = new \DateTime();

        if (!$this->deadline) {
            return $dateTime;
        } else {
            return $dateTime->modify('+' . $this->deadline . ' days');
        }
    }

    /**
     * Is product in stock.
     *
     * @param int $nodeId
     * @return boolean
     */
    public function isInStock($nodeId, \DateTime $date = null)
    {
        if ($date) {
            $deliveryLink = $this->deliveryLink($nodeId, $date->format('Y-m-d'));

            if (!$deliveryLink) {
                return false;
            }

            return (bool) $deliveryLink->getAvailableQuantity();
        } else {
            $dates = null;

            if ($date) {
                $dates = new Collection();
                $dates->push($date);
            }

            $deliveryLinks = $this->deliveryLinks($nodeId, $dates);

            if ($deliveryLinks->count() === 0) {
                return false;
            }

            $hasQuantity = (bool) $deliveryLinks->map(function($deliveryLink) {
                return (int) $deliveryLink->getAvailableQuantity();
            })->sum();

            return $hasQuantity;
        }
    }

    /**
     * Get info to be stored with order.
     *
     * @return array
     */
    public function getInfoForOrder()
    {
        return [
            'id' => $this->id,
            'producer_id' => $this->producer_id,
            'name' => $this->name,
            'price_unit' => $this->price_unit,
            'price' => $this->price,
            'package_unit' => $this->package_unit,
            'package_amount' => $this->package_amount,
            'is_hidden' => $this->hidden,
            'deadline' => $this->deadline,
            'payment_info' => $this->payment_info,
            'production_type' => $this->production_type,
        ];
    }
}
