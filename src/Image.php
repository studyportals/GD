<?php

/**
 * @file GD/Image.php
 *
 * @author Thijs Putman <thijs@studyportals.eu>
 * @copyright © 2005-2009 Thijs Putman, all rights reserved.
 * @copyright © 2010-2012 StudyPortals B.V., all rights reserved.
 * @version 1.3.0
 */

namespace StudyPortals\GD;

/**
 * @class Image
 * GD-based image manipulation class.
 *
 * <p>Supports JPEG, PNG and GIF image formats.</p>
 *
 * @package StudyPortals.Framework
 * @subpackage GD
 */

class Image{

	protected $_file;
	protected $_file_type;
	protected $_file_sizes = [];

	protected $_quality = 80;
	protected $_image;

	/**
	 * Creates an image object from a FileSystemFile.
	 *
	 * <p>The {@link $file} argument passed into this method should be the
	 * location of a valid JPEG, PNG or GIF image.</p>
	 *
	 * @param string $file
	 *
	 * @throws ImageException
	 */

	public function __construct($file){

		$file_base = basename($file);

		if(!file_exists($file)){

			throw new ImageException("Failed to open $file_base, file not found");
		}

 		$this->_file = $file;

 		list($width, $height, $this->_file_type) = (array) @getimagesize($this->_file);
 		$this->_file_sizes = [$width, $height];

		if(empty($width) || empty($height)){

			throw new ImageException("File $file_base does not appear to be an image");
		}

		switch($this->_file_type){

			case IMAGETYPE_JPEG:

				$this->_image = ImageCreateFromJPEG($this->_file);

			break;

			case IMAGETYPE_PNG:

				$this->_image = ImageCreateFromPNG($this->_file);

			break;

			case IMAGETYPE_GIF:

				$this->_image = ImageCreateFromGIF($this->_file);

			break;

			default:

				throw new ImageException("Unable to read from $file_base, unsupported format");
		}
	}

	/**
	 * Set JPEG-quality for the current image.
	 *
	 * <p>By default quality is set to 80. Quality applies to all desctructive
	 * modifications applied to the image.</p>
	 *
	 * @param integer $quality [1-100]
	 * @return void
	 * @throws ImageException
	 */

	public function setJPEGQuality($quality){

		if($this->_file_type !== IMAGETYPE_JPEG){

			throw new ImageException('Unable to set JPEG-quality for non-JPEG file');
		}

		$this->_quality = (int) $quality;
	}

	/**
	 * Return the actual image data.
	 *
	 * <p>Takes a GD resource pointer and returns the image data.</p>
	 *
	 * @param resource $resource
	 *
	 * @throws ImageException
	 * @return string Binary image data
	 */

	protected function _outputImage($resource){

		ob_start();

		switch($this->_file_type){

			case IMAGETYPE_JPEG:

				$result = ImageJPEG($resource, null, $this->_quality);

			break;

			case IMAGETYPE_PNG:

				$result = ImagePNG($resource);

			break;

			case IMAGETYPE_GIF:

				$result = ImageGIF($resource);

			break;
		}

		if(empty($result)){

			ob_end_clean();

			$file_base = basename($this->_file);

			throw new ImageException("Unknown error while generating output for image $file_base");
		}

		return ob_get_clean();
	}

	/**
	 * Resize the image.
	 *
	 * <p>Resizes the image to the given dimensions. If one of the dimensions
	 * is set to <i>null</i> it will be automatically calculated in such a way
	 * that the resize operation maintains the original proportions of the
	 * image.</p>
	 *
	 * <p>This method will always maintain the proportions of the original image.
	 * If the dimensions specified result in different proportions, the excess
	 * parts of the original image are cut away.</p>
	 *
	 * @param integer $width
	 * @param integer $height
	 * @return string Binary image data
	 * @throws ImageException
	 */

	protected function _resize($width = null, $height = null){

		if(empty($width) && empty($height)){

			throw new ImageException('Cannot resize an image to zero by zero pixels');
		}

		// Setup all arguments we're going to need

		$dst_x = 0;
		$dst_y = 0;
		$src_x = 0;
		$src_y = 0;
		$dst_w = (int) $width;
		$dst_h = (int) $height;
		$src_w = $this->_file_sizes[0];
		$src_h = $this->_file_sizes[1];

		assert('$src_w > 0 && src_h > 0');
		if(empty($src_w) || empty($src_h)){

			$file_base = basename($this->_file);

			throw new ImageException("Unable to resize $file_base, on of its dimensions is zero");
		}

		$aspect = $src_w / $src_h;

		// Either width or height is missing; compute it based on the aspect-ratio

		if(empty($dst_w) xor empty($dst_h)){

			if(empty($dst_w)){

				$dst_w = $dst_h * $aspect;
			}
			else{

				$dst_h = $dst_w * (1 / $aspect);
			}
		}

		// Equal width and height

		if($dst_w == $dst_h){

			if($aspect > 1){

				$src_x = ($src_w - $src_h) / 2;
				$src_w -= $src_x;
			}
			else{

				$src_y = ($src_w - $src_h) / 2;
				$src_h -= $src_y;
			}
		}

		// Unequal width and height

		else{

			$dst_aspect = $dst_w / $dst_h;

			// Cut the height to make it fit

			if($dst_aspect > $aspect){

				$ratio = $src_w / $dst_w;
				$src_y = ($src_h - ($dst_h * $ratio)) / 2;
				$src_h = $src_h - (2 * $src_y);
			}

			// Cut the width to make it fit

			else{

				$ratio = $src_h / $dst_h;
				$src_x = ($src_w - ($dst_w * $ratio)) / 2;
				$src_w = $src_w - (2 * $src_x);
			}
		}

		// Execute the resize operation

		$resized = ImageCreateTrueColor($dst_w, $dst_h);

		ImageCopyResampled($resized, $this->_image, $dst_x, $dst_y, $src_x,
			$src_y, $dst_w, $dst_h, $src_w, $src_h);

		// Output

		return $this->_outputImage($resized);
	}

	/**
	 * Resize the current image.
	 *
	 * <p>This method resizes the current image to the specified dimensions.
	 * Aspect-ratio is taken into account and parts of the image will be cut
	 * in case the resized copy doesn't fit the original aspect-ration.</p>
	 *
	 * @param integer $width
	 * @param integer $height
	 * @return string
	 * @throws ImageException
	 * @see Image::_resize()
	 */

	public function resize($width, $height){

		try{

			$resized = $this->_resize($width, $height);

			return $resized;
		}
		catch(ImageException $e){

			throw new ImageException('Unable to resize: ' . $e->getMessage(), 0, $e);
		}
	}
}