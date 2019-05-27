<?php

namespace haringsrob\Icecat\Model;

/**
 * Parses the result data into a usable object.
 */
class Result implements ResultInterface
{

    /**
     * The actual data we fetched. To get the data you can use.
     *
     * @var \stdClass
     */
    private $data;

    /**
     * The images as an array.
     *
     * @var array
     */
    private $images = [];
    
    /**
     * The multimedia objects as an array.
     *
     * @var array
     */
    private $multimediaObjects = [];

    /**
     * Icecat Constructor.
     *
     * @todo: validation.
     *
     * @param \SimpleXMLElement $data
     */
    public function __construct($data)
    {
        $this->setBaseData($data);
    }

    /**
     * @inheritdoc
     */
    private function setBaseData($data)
    {
        $this->data = json_decode(json_encode($data));
    }

    /**
     * @inheritdoc
     */
    public function getBaseData()
    {
        return $this->data;
    }

    /**
     * Gets all attributes.
     */
    public function getAttributes()
    {
        return $this->getProductData()->{'@attributes'};
    }

    /**
     * Gets a specific attribute.
     *
     * @param string $attribute
     *
     * @return string
     */
    public function getAttribute($attribute)
    {
        return $this->getAttributes()->$attribute;
    }

    /**
     * Gets the supplier.
     *
     * @return string
     */
    public function getSupplier()
    {
        return $this->getProductData()->Supplier->{'@attributes'}->Name;
    }

    /**
     * Gets the long description.
     *
     * @return string
     */
    public function getLongDescription()
    {
        if (!empty($this->getProductData()->ProductDescription->{'@attributes'})) {
            return $this->getProductData()->ProductDescription->{'@attributes'}->LongDesc;
        } else {
            return '';
        }
    }

    /**
     * Gets the short description.
     *
     * @return string
     */
    public function getShortDescription()
    {
        if (!empty($this->getProductData()->ProductDescription->{'@attributes'})) {
            return $this->getProductData()->ProductDescription->{'@attributes'}->ShortDesc;
        } else {
            return '';
        }
    }

    /**
     * Gets the product category.
     *
     * @return string
     */
    public function getCategory()
    {
        return $this->getProductData()->Category->Name->{'@attributes'}->Value;
    }

    /**
     * Gets an array of images.
     *
     * @return array
     */
    public function getImages()
    {
        if (empty($this->images)) {
            if ($this->productHasImageGallery()) {
                $productPictures = $this->getProductData()->ProductGallery->ProductPicture;
                // Make sure $productPictures is an array.
                if (!is_array($productPictures)){
                        $productPictures = [$productPictures];
                }

                foreach ($productPictures as $img) {
                    $attr = $img->{'@attributes'};
                    $this->images[] = [
                        'high'  => $attr->Pic,
                        'low'   => $attr->LowPic,
                        'thumb' => $attr->ThumbPic,
                    ];
                }
            }
            if ($this->productHasMainImage()) {
                $this->images[] = [
                    'high'  => $this->getProductData()->{'@attributes'}->HighPic,
                    'low'   => $this->getProductData()->{'@attributes'}->LowPic,
                    'thumb' => $this->getProductData()->{'@attributes'}->ThumbPic,
                ];
            }
        }

        return $this->images;
    }

    /**
     * Checks if the product has an image gallery.
     *
     * @return bool
     */
    private function productHasImageGallery()
    {
        return !empty($this->getProductData()->ProductGallery->ProductPicture);
    }

    /**
     * Checks if the product has a Main Image.
     *
     * @return bool
     */
    private function productHasMainImage()
    {
        return !empty($this->getProductData()->{'@attributes'}->HighPic);
    }
    
    /**
     * Gets an array of multimedia Objects.
     *
     * @return array
     */
    public function getMultimediaObjects()
    {
        if (empty($this->multimediaObjects)) {
            if ($this->productHasMultimediaObject()) {
                $productMultimediaObjects = $this->getProductData()->ProductMultimediaObject->MultimediaObject;
                // Make sure $productMultimediaObjects is an array.
                if (!is_array($productMultimediaObjects)){
                        $productMultimediaObjects = [$productMultimediaObjects];
                }
                 foreach ($productMultimediaObjects as $productMultimediaObject) {
                    $attr = $productMultimediaObject->{'@attributes'};
                    $multimediaObject = [
                        'contentType'  => $attr->ContentType,
                        'description'  => $attr->Description,
                        'type'         => $attr->Type,
                        'size'         => (!empty($attr->Size) ? $attr->Size : 0),
                        'url'          => (!empty($attr->URL) ? $attr->URL : ''),
                        'langId'       => $attr->langid,
                     ];

                    // retrieve 360 images?
                    if ($attr->Type == '360') {
                        $images360 = [];

                        foreach ($productMultimediaObject->ImagesList360->Image as $image) {
                            $attr = $image->{'@attributes'};
                            $images360[(int) $attr->No] = $attr->Link;
                        }
                        $multimediaObject['image360'] = $images360;
                    }

                    $this->multimediaObjects[] = $$multimediaObject;
                }
            }
        }
         return $this->multimediaObjects;
    }
    
    /**
     * Checks if the product has multimedia objects.
     *
     * @return bool
     */
    private function productHasMultimediaObject()
    {
        return !empty($this->getProductData()->ProductMultimediaObject->MultimediaObject);
    }
    
    /**
     * Checks if the product has Product Features.
     *
     * @return bool
     */
    private function productHasProductFeature()
    {
        return !empty($this->getProductData()->ProductFeature);
    }

    /**
     * Gets a specification by its identifier.
     *
     * @param integer $identifier
     *   The ID of the specification.
     *
     * @return mixed
     *   The content of the specification.
     */
    public function getSpecByIdentifier($identifier)
    {
        if ($this->productHasProductFeature()) {
            $productFeature = $this->getProductData()->ProductFeature;
            
            // Make sure $productFeature is an array.
            if (!is_array($productFeature)) {
                $productFeature = [$productFeature];
            }
            foreach ($productFeature as $key => $feature) {
                if ($feature->{'@attributes'}->CategoryFeature_ID === $identifier) {
                    return $feature->{'@attributes'}->Presentation_Value;
                }
            }
        }
        return null;
    }

    /**
     * Gets a specification by name.
     *
     * @param string $specificationName
     *
     * @return mixed
     *   The content of the specification.
     */
    public function getSpecByName($specificationName)
    {
        if ($this->productHasProductFeature()) {
            $productFeature = $this->getProductData()->ProductFeature;
            
            // Make sure $productFeature is an array.
            if (!is_array($productFeature)) {
                $productFeature = [$productFeature];
            }
            foreach ($productFeature as $key => $feature) {
                if (strtolower($feature->Feature->Name->{'@attributes'}->Value) === strtolower($specificationName)) {
                    return $feature->{'@attributes'}->Presentation_Value;
                }
            }
        }
        return null;
    }

    /**
     * Gets an array of specifications.
     *
     * @return array
     */
    public function getSpecs()
    {
        $specifications = [];
        
        if ($this->productHasProductFeature()) {
            $productFeature = $this->getProductData()->ProductFeature;
            
            // Make sure $productFeature is an array.
            if (!is_array($productFeature)) {
                $productFeature = [$productFeature];
            }
            foreach ($productFeature as $key => $feature) {
                $specifications[$key]['feature_id'] = $feature->Feature->{'@attributes'}->ID;
                $specifications[$key]['name'] = $feature->Feature->Name->{'@attributes'}->Value;
                $specifications[$key]['data'] = $feature->{'@attributes'}->Presentation_Value;
                $specifications[$key]['spec_id'] = $feature->{'@attributes'}->CategoryFeature_ID;
            }
        }

        return $specifications;
    }

    /**
     * Gets all product data.
     *
     * @return \stdClass
     */
    public function getProductData()
    {
        return $this->data->Product;
    }

}
