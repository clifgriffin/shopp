<?php
/**
 * ImageProcessor class
 * 
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 17 April, 2008
 * @package Shopp
 **/

class ImageProcessor {
	var $src;
	var $processed;
	var $alpha = false;
	
	function ImageProcessor ($data,$width,$height) {
		$this->src = new StdClass();
		$this->src->width = $width;
		$this->src->height = $height;
		$this->src->image = imagecreatefromstring($data);
	}
		
	function scale ($width,$height,$fit='all',$alpha=false,$fill=false) {
		$dx = $dy = 0;
		$xd = false;
		
		// Allocate a new true color image
		$this->processed = ImageCreateTrueColor($width,$height);
		if ($alpha) {
			ImageAlphaBlending($this->processed, false);
			$transparent = ImageColorAllocateAlpha($this->processed, 0, 0, 0, 127);
			ImageFill($this->processed, 0, 0, $transparent);
			ImageSaveAlpha($this->processed, true);
			$this->alpha = true;
		}

		if ($fit == "crop" || $fit = "matte") {
			// Determine the extra dimension for positioning
			$xd = false;
			$xd = ($this->src->width > $this->src->height && $fit == "crop");
			$xd = ($this->src->width < $this->src->height && $fit == "matte");
			
			if ($xd) {
				$dw = $this->_proportionalWidth($height);
				$dx = ($dw - $width)*-0.5;	
				$width = $dw;
			} else {
				$dh = $this->_proportionalHeight($width);
				$dy = ($dh - $height)*-0.5;
				$height = $dh;
			}
		}
		
		// Fill image with matte color
		if ($fill !== false || $fit == "matte") {
			// Default to white
			if (!is_array($fill)) $fill = array(255,255,255);
			
			// Mix the color
			$matte = ImageColorAllocate($this->processed, $fill[0], $fill[1], $fill[2]);
			
			// Fill the canvas
			ImageFill($this->processed,0,0,$matte);
		}

		ImageCopyResampled(
			$this->processed,$this->src->image,
			$dx, $dy, 								// dest_x, dest_y
			0, 0, 									// src_x, src_y
			$width, $height, 						// dest_width, dest_height
			$this->src->width, $this->src->height	// src_width, src_height
		);
		
	}
	
	function _proportionalWidth ($height) {
		$s_height = $height/$this->src->height;
		return ceil($this->src->width * $s_height);
	}
	
	function _proportionalHeight ($width) {
		$s_width = $width/$this->src->width;
		return ceil($this->src->height * $s_width);
	}
	
	/**
	 * Return the processed image
	 */
	function imagefile ($quality=80) {
		if (!isset($this->processed)) $image =& $this->src->image;
		else $image = &$this->processed;
		
		imageinterlace($image, true);						// For progressive loading
		ob_start();  										// Start capturing output buffer stream
		if ($this->alpha) imagepng($image);					// Output the image to the stream
		else imagejpeg($image,NULL,$quality);				
		$buffer = ob_get_contents(); 						// Get the bugger
		ob_end_clean(); 									// Clear the buffer
		return $buffer;										// Send it back
	}
	
	/**
	 * UnsharpMask ()
	 * version 2.1.1
	 * Unsharp mask algorithm by Torstein Hansi <thoensi_at_netcom_dot_no>, July 2003
	 **/
	function UnsharpMask ($amount=50, $radius=0.5, $threshold=3) {  
		if (!isset($this->Processed->image)) $image = &$this->src->image;
		else $image = &$this->Processed->image;

	    // Attempt to calibrate the parameters to Photoshop
	    if ($amount > 500) $amount = 500;
	    $amount = $amount * 0.016;
	    if ($radius > 50) $radius = 50;
	    $radius = $radius * 2;
	    if ($threshold > 255) $threshold = 255;

	    $radius = abs(round($radius));
	    if ($radius == 0) return $image;
	    $w = imagesx($image); $h = imagesy($image);
	    $canvas = imagecreatetruecolor($w, $h);
	    $blur = imagecreatetruecolor($w, $h);

	    /**
	     * Gaussian blur matrix:
		 *	1    2    1
		 *	2    4    2
	     *	1    2    1          
		 **/

	    if (function_exists('imageconvolution')) { // PHP >= 5.1   
            $matrix = array(
				array( 1, 2, 1 ),   
            	array( 2, 4, 2 ),   
            	array( 1, 2, 1 )   
        	);   
	        imagecopy ($blur, $image, 0, 0, 0, 0, $w, $h);  
	        imageconvolution($blur, $matrix, 16, 0);   
	    } else {   

			// Move copies of the image around one pixel at the time and merge them with weight  
			// according to the matrix. The same matrix is simply repeated for higher radii.  
	        for ($i = 0; $i < $radius; $i++)    {  
	            imagecopy ($blur, $image, 0, 0, 1, 0, $w - 1, $h); // left  
	            imagecopymerge ($blur, $image, 1, 0, 0, 0, $w, $h, 50); // right  
	            imagecopymerge ($blur, $image, 0, 0, 0, 0, $w, $h, 50); // center  
	            imagecopy ($canvas, $blur, 0, 0, 0, 0, $w, $h);  

	            imagecopymerge ($blur, $canvas, 0, 0, 0, 1, $w, $h - 1, 33.33333 ); // up  
	            imagecopymerge ($blur, $canvas, 0, 1, 0, 0, $w, $h, 25); // down  
	        }  
	    }  

	    if ($threshold > 0){  
	        // Calculate the difference between the blurred pixels and the original  
	        // and set the pixels  
	        for ($x = 0; $x < $w-1; $x++) { // each row 
	            for ($y = 0; $y < $h; $y++) { // each pixel  

	                $rgbOrig = ImageColorAt($image, $x, $y);  
	                $rOrig = (($rgbOrig >> 16) & 0xFF);  
	                $gOrig = (($rgbOrig >> 8) & 0xFF);  
	                $bOrig = ($rgbOrig & 0xFF);  

	                $rgbBlur = ImageColorAt($blur, $x, $y);  

	                $rBlur = (($rgbBlur >> 16) & 0xFF);  
	                $gBlur = (($rgbBlur >> 8) & 0xFF);  
	                $bBlur = ($rgbBlur & 0xFF);  

	                // When the masked pixels differ less from the original  
	                // than the threshold specifies, they are set to their original value.  
	                $rNew = (abs($rOrig - $rBlur) >= $threshold)   
	                    ? max(0, min(255, ($amount * ($rOrig - $rBlur)) + $rOrig))   
	                    : $rOrig;
	                $gNew = (abs($gOrig - $gBlur) >= $threshold)   
	                    ? max(0, min(255, ($amount * ($gOrig - $gBlur)) + $gOrig))   
	                    : $gOrig;
	                $bNew = (abs($bOrig - $bBlur) >= $threshold)   
	                    ? max(0, min(255, ($amount * ($bOrig - $bBlur)) + $bOrig))   
	                    : $bOrig;



	                if (($rOrig != $rNew) || ($gOrig != $gNew) || ($bOrig != $bNew)) {  
	                        $pixCol = ImageColorAllocate($image, $rNew, $gNew, $bNew);  
	                        ImageSetPixel($image, $x, $y, $pixCol);  
	                    }  
	            }  
	        }  
	    } else {  
	        for ($x = 0; $x < $w; $x++) { // each row  
	            for ($y = 0; $y < $h; $y++) { // each pixel  
	                $rgbOrig = ImageColorAt($image, $x, $y);  
	                $rOrig = (($rgbOrig >> 16) & 0xFF);  
	                $gOrig = (($rgbOrig >> 8) & 0xFF);  
	                $bOrig = ($rgbOrig & 0xFF);  

	                $rgbBlur = ImageColorAt($blur, $x, $y);  

	                $rBlur = (($rgbBlur >> 16) & 0xFF);  
	                $gBlur = (($rgbBlur >> 8) & 0xFF);  
	                $bBlur = ($rgbBlur & 0xFF);  

	                $rNew = ($amount * ($rOrig - $rBlur)) + $rOrig;  
	                    if ($rNew > 255) $rNew=255;
	                    elseif($rNew < 0) $rNew=0; 
	                $gNew = ($amount * ($gOrig - $gBlur)) + $gOrig;  
	                    if ($gNew > 255) $gNew=255;
	                    elseif($gNew < 0) $gNew=0; 
	                $bNew = ($amount * ($bOrig - $bBlur)) + $bOrig;  
	                    if($bNew > 255) $bNew=255;  
	                    elseif($bNew < 0) $bNew=0; 
	                $rgbNew = ($rNew << 16) + ($gNew <<8) + $bNew;  
	                    ImageSetPixel($image, $x, $y, $rgbNew);  
	            }
	        }
	    }
	    imagedestroy($canvas);  
	    imagedestroy($blur);  

	}

} // END class ImageProcessor

?>