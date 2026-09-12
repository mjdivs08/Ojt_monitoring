<?php

/*
|--------------------------------------------------------------------------
| PHP GD SIGNATURE SIMILARITY VERIFICATION
|--------------------------------------------------------------------------
|
| This performs visual similarity checking only.
| It does NOT determine with certainty whether a signature is forged.
|
*/


/*
|--------------------------------------------------------------------------
| GD CHECK
|--------------------------------------------------------------------------
*/

function sigGDInstalled(): bool
{
    return extension_loaded('gd');
}


/*
|--------------------------------------------------------------------------
| LOAD IMAGE
|--------------------------------------------------------------------------
*/

function sigLoadImage(string $path)
{
    if (!is_file($path)) {
        return false;
    }

    $info = @getimagesize($path);

    if (
        !$info ||
        empty($info['mime'])
    ) {
        return false;
    }

    switch ($info['mime']) {

        case 'image/jpeg':

            return @imagecreatefromjpeg(
                $path
            );


        case 'image/png':

            $source =
                @imagecreatefrompng(
                    $path
                );

            if (!$source) {
                return false;
            }

            /*
            |--------------------------------------------------------------------------
            | Flatten PNG transparency onto white
            |--------------------------------------------------------------------------
            */

            $canvas =
                imagecreatetruecolor(
                    imagesx($source),
                    imagesy($source)
                );

            $white =
                imagecolorallocate(
                    $canvas,
                    255,
                    255,
                    255
                );

            imagefill(
                $canvas,
                0,
                0,
                $white
            );

            imagecopy(
                $canvas,
                $source,
                0,
                0,
                0,
                0,
                imagesx($source),
                imagesy($source)
            );

            imagedestroy(
                $source
            );

            return $canvas;


        case 'image/webp':

            if (
                function_exists(
                    'imagecreatefromwebp'
                )
            ) {

                return
                    @imagecreatefromwebp(
                        $path
                    );
            }

            return false;


        default:

            return false;
    }
}


/*
|--------------------------------------------------------------------------
| CROP SELECTED REGION
|--------------------------------------------------------------------------
|
| x/y/width/height are normalized values from 0 to 1.
|
*/

function sigCropSelectedArea(
    string $imagePath,
    float $normalX,
    float $normalY,
    float $normalWidth,
    float $normalHeight
) {

    $image =
        sigLoadImage(
            $imagePath
        );

    if (!$image) {
        return false;
    }


    $imageWidth =
        imagesx($image);

    $imageHeight =
        imagesy($image);


    $x =
        (int)round(
            $normalX
            * $imageWidth
        );

    $y =
        (int)round(
            $normalY
            * $imageHeight
        );

    $width =
        (int)round(
            $normalWidth
            * $imageWidth
        );

    $height =
        (int)round(
            $normalHeight
            * $imageHeight
        );


    /*
    |--------------------------------------------------------------------------
    | Clamp inside image
    |--------------------------------------------------------------------------
    */

    $x =
        max(
            0,
            min(
                $x,
                $imageWidth - 1
            )
        );


    $y =
        max(
            0,
            min(
                $y,
                $imageHeight - 1
            )
        );


    $width =
        min(
            $width,
            $imageWidth - $x
        );


    $height =
        min(
            $height,
            $imageHeight - $y
        );


    if (
        $width < 20 ||
        $height < 10
    ) {

        imagedestroy(
            $image
        );

        return false;
    }


    $crop =
        imagecrop(
            $image,
            [
                'x' => $x,
                'y' => $y,
                'width' => $width,
                'height' => $height
            ]
        );


    imagedestroy(
        $image
    );


    return $crop;
}


/*
|--------------------------------------------------------------------------
| DETECT DARK INK
|--------------------------------------------------------------------------
*/

function sigFindInkBounds(
    $image,
    int $threshold = 190
): ?array {

    if (!$image) {
        return null;
    }


    $width =
        imagesx($image);

    $height =
        imagesy($image);


    $minX = $width;
    $minY = $height;

    $maxX = -1;
    $maxY = -1;


    /*
    |--------------------------------------------------------------------------
    | Resize huge input before expensive detection
    |--------------------------------------------------------------------------
    */

    $step = 1;

    if (
        $width > 1200 ||
        $height > 1200
    ) {
        $step = 2;
    }


    for (
        $y = 0;
        $y < $height;
        $y += $step
    ) {

        for (
            $x = 0;
            $x < $width;
            $x += $step
        ) {

            $rgb =
                imagecolorat(
                    $image,
                    $x,
                    $y
                );


            $r =
                ($rgb >> 16)
                & 255;

            $g =
                ($rgb >> 8)
                & 255;

            $b =
                $rgb
                & 255;


            $gray =
                (
                    0.299 * $r
                    +
                    0.587 * $g
                    +
                    0.114 * $b
                );


            if (
                $gray
                <
                $threshold
            ) {

                $minX =
                    min(
                        $minX,
                        $x
                    );

                $minY =
                    min(
                        $minY,
                        $y
                    );

                $maxX =
                    max(
                        $maxX,
                        $x
                    );

                $maxY =
                    max(
                        $maxY,
                        $y
                    );
            }
        }
    }


    if (
        $maxX < 0 ||
        $maxY < 0
    ) {
        return null;
    }


    return [
        'x' => $minX,
        'y' => $minY,

        'width' =>
            ($maxX - $minX) + 1,

        'height' =>
            ($maxY - $minY) + 1
    ];
}


/*
|--------------------------------------------------------------------------
| CROP TO ACTUAL SIGNATURE INK
|--------------------------------------------------------------------------
*/

function sigCropToInk(
    $image,
    int $padding = 8
) {

    $bounds =
        sigFindInkBounds(
            $image
        );


    if (!$bounds) {
        return false;
    }


    $imageWidth =
        imagesx($image);

    $imageHeight =
        imagesy($image);


    $x =
        max(
            0,
            $bounds['x']
            - $padding
        );


    $y =
        max(
            0,
            $bounds['y']
            - $padding
        );


    $width =
        min(
            $imageWidth - $x,

            $bounds['width']
            + ($padding * 2)
        );


    $height =
        min(
            $imageHeight - $y,

            $bounds['height']
            + ($padding * 2)
        );


    if (
        $width <= 5 ||
        $height <= 5
    ) {
        return false;
    }


    return imagecrop(
        $image,
        [
            'x' => $x,
            'y' => $y,
            'width' => $width,
            'height' => $height
        ]
    );
}


/*
|--------------------------------------------------------------------------
| NORMALIZE
|--------------------------------------------------------------------------
*/

function sigNormalize(
    $image,
    int $targetWidth = 300,
    int $targetHeight = 120
) {

    if (!$image) {
        return false;
    }


    $trimmed =
        sigCropToInk(
            $image
        );


    if (!$trimmed) {
        return false;
    }


    $sourceWidth =
        imagesx($trimmed);

    $sourceHeight =
        imagesy($trimmed);


    if (
        $sourceWidth <= 0 ||
        $sourceHeight <= 0
    ) {

        imagedestroy(
            $trimmed
        );

        return false;
    }


    $canvas =
        imagecreatetruecolor(
            $targetWidth,
            $targetHeight
        );


    $white =
        imagecolorallocate(
            $canvas,
            255,
            255,
            255
        );


    imagefill(
        $canvas,
        0,
        0,
        $white
    );


    /*
    |--------------------------------------------------------------------------
    | Keep signature proportions
    |--------------------------------------------------------------------------
    */

    $usableWidth =
        $targetWidth - 20;

    $usableHeight =
        $targetHeight - 20;


    $scale =
        min(
            $usableWidth
            / $sourceWidth,

            $usableHeight
            / $sourceHeight
        );


    $newWidth =
        max(
            1,
            (int)round(
                $sourceWidth
                * $scale
            )
        );


    $newHeight =
        max(
            1,
            (int)round(
                $sourceHeight
                * $scale
            )
        );


    $destinationX =
        (int)(
            (
                $targetWidth
                - $newWidth
            )
            / 2
        );


    $destinationY =
        (int)(
            (
                $targetHeight
                - $newHeight
            )
            / 2
        );


    imagecopyresampled(
        $canvas,
        $trimmed,

        $destinationX,
        $destinationY,

        0,
        0,

        $newWidth,
        $newHeight,

        $sourceWidth,
        $sourceHeight
    );


    imagedestroy(
        $trimmed
    );


    return $canvas;
}


/*
|--------------------------------------------------------------------------
| BINARY MATRIX
|--------------------------------------------------------------------------
*/

function sigBinaryMatrix(
    $image,
    int $threshold = 180
): array {

    $width =
        imagesx($image);

    $height =
        imagesy($image);


    $matrix = [];


    for (
        $y = 0;
        $y < $height;
        $y++
    ) {

        $row = [];


        for (
            $x = 0;
            $x < $width;
            $x++
        ) {

            $rgb =
                imagecolorat(
                    $image,
                    $x,
                    $y
                );


            $r =
                ($rgb >> 16)
                & 255;

            $g =
                ($rgb >> 8)
                & 255;

            $b =
                $rgb
                & 255;


            $gray =
                (
                    0.299 * $r
                    +
                    0.587 * $g
                    +
                    0.114 * $b
                );


            $row[] =
                $gray < $threshold
                ? 1
                : 0;
        }


        $matrix[] =
            $row;
    }


    return $matrix;
}


/*
|--------------------------------------------------------------------------
| MATRIX PIXEL
|--------------------------------------------------------------------------
*/

function sigMatrixPixel(
    array $matrix,
    int $x,
    int $y
): int {

    if (
        $x < 0 ||
        $y < 0
    ) {
        return 0;
    }


    return
        !empty(
            $matrix[$y][$x]
        )
        ? 1
        : 0;
}


/*
|--------------------------------------------------------------------------
| DICE SCORE
|--------------------------------------------------------------------------
*/

function sigDiceAtOffset(
    array $reference,
    array $submitted,
    int $offsetX,
    int $offsetY
): float {

    $height =
        count(
            $reference
        );


    if ($height === 0) {
        return 0;
    }


    $width =
        count(
            $reference[0]
        );


    $referenceInk = 0;
    $submittedInk = 0;
    $intersection = 0;


    for (
        $y = 0;
        $y < $height;
        $y++
    ) {

        for (
            $x = 0;
            $x < $width;
            $x++
        ) {

            $a =
                sigMatrixPixel(
                    $reference,
                    $x,
                    $y
                );


            $b =
                sigMatrixPixel(
                    $submitted,
                    $x + $offsetX,
                    $y + $offsetY
                );


            if ($a) {
                $referenceInk++;
            }


            if ($b) {
                $submittedInk++;
            }


            if (
                $a &&
                $b
            ) {
                $intersection++;
            }
        }
    }


    $denominator =
        $referenceInk
        +
        $submittedInk;


    if ($denominator <= 0) {
        return 0;
    }


    return (
        (
            2
            * $intersection
        )
        /
        $denominator
    )
    * 100;
}


/*
|--------------------------------------------------------------------------
| BEST SMALL POSITION SHIFT
|--------------------------------------------------------------------------
*/

function sigBestDiceScore(
    array $reference,
    array $submitted
): float {

    $best = 0;


    for (
        $offsetY = -6;
        $offsetY <= 6;
        $offsetY += 2
    ) {

        for (
            $offsetX = -8;
            $offsetX <= 8;
            $offsetX += 2
        ) {

            $score =
                sigDiceAtOffset(
                    $reference,
                    $submitted,
                    $offsetX,
                    $offsetY
                );


            if (
                $score > $best
            ) {

                $best =
                    $score;
            }
        }
    }


    return $best;
}


/*
|--------------------------------------------------------------------------
| INK COUNT
|--------------------------------------------------------------------------
*/

function sigInkCount(
    array $matrix
): int {

    $count = 0;


    foreach (
        $matrix
        as $row
    ) {

        foreach (
            $row
            as $pixel
        ) {

            if ($pixel) {
                $count++;
            }
        }
    }


    return $count;
}


/*
|--------------------------------------------------------------------------
| DENSITY SCORE
|--------------------------------------------------------------------------
*/

function sigDensitySimilarity(
    array $reference,
    array $submitted
): float {

    $referenceInk =
        sigInkCount(
            $reference
        );


    $submittedInk =
        sigInkCount(
            $submitted
        );


    if (
        $referenceInk <= 0 ||
        $submittedInk <= 0
    ) {
        return 0;
    }


    return (
        min(
            $referenceInk,
            $submittedInk
        )
        /
        max(
            $referenceInk,
            $submittedInk
        )
    )
    * 100;
}


/*
|--------------------------------------------------------------------------
| FINAL VERIFICATION
|--------------------------------------------------------------------------
*/

function verifySelectedSignature(
    string $referencePath,
    string $documentPath,
    float $x,
    float $y,
    float $width,
    float $height
): array {

    /*
    |--------------------------------------------------------------------------
    | Validate normalized coordinates
    |--------------------------------------------------------------------------
    */

    if (
        $x < 0 ||
        $y < 0 ||
        $width <= 0 ||
        $height <= 0 ||

        $x > 1 ||
        $y > 1 ||
        $width > 1 ||
        $height > 1 ||

        ($x + $width) > 1.01 ||
        ($y + $height) > 1.01
    ) {

        return [
            'success' => false,
            'similarity' => null,
            'result' =>
                'Invalid signature selection.'
        ];
    }


    if (
        !sigGDInstalled()
    ) {

        return [
            'success' => false,
            'similarity' => null,
            'result' =>
                'GD extension is not enabled.'
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Load reference
    |--------------------------------------------------------------------------
    */

    $referenceOriginal =
        sigLoadImage(
            $referencePath
        );


    if (!$referenceOriginal) {

        return [
            'success' => false,
            'similarity' => null,
            'result' =>
                'Company reference signature could not be loaded.'
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Crop selected signature from COMPLETE DTR
    |--------------------------------------------------------------------------
    */

    $submittedOriginal =
        sigCropSelectedArea(
            $documentPath,
            $x,
            $y,
            $width,
            $height
        );


    if (!$submittedOriginal) {

        imagedestroy(
            $referenceOriginal
        );


        return [
            'success' => false,
            'similarity' => null,
            'result' =>
                'Selected signature area could not be processed.'
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Normalize
    |--------------------------------------------------------------------------
    */

    $reference =
        sigNormalize(
            $referenceOriginal
        );


    $submitted =
        sigNormalize(
            $submittedOriginal
        );


    imagedestroy(
        $referenceOriginal
    );


    imagedestroy(
        $submittedOriginal
    );


    if (
        !$reference ||
        !$submitted
    ) {

        if ($reference) {
            imagedestroy(
                $reference
            );
        }

        if ($submitted) {
            imagedestroy(
                $submitted
            );
        }


        return [
            'success' => false,
            'similarity' => null,
            'result' =>
                'Unable to detect enough signature detail.'
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Binary matrices
    |--------------------------------------------------------------------------
    */

    $referenceMatrix =
        sigBinaryMatrix(
            $reference
        );


    $submittedMatrix =
        sigBinaryMatrix(
            $submitted
        );


    /*
    |--------------------------------------------------------------------------
    | Compare
    |--------------------------------------------------------------------------
    */

    $shapeScore =
        sigBestDiceScore(
            $referenceMatrix,
            $submittedMatrix
        );


    $densityScore =
        sigDensitySimilarity(
            $referenceMatrix,
            $submittedMatrix
        );


    /*
    |--------------------------------------------------------------------------
    | 85% shape + 15% ink density
    |--------------------------------------------------------------------------
    */

    $score =
        (
            $shapeScore * 0.85
        )
        +
        (
            $densityScore * 0.15
        );


    $score =
        round(
            max(
                0,
                min(
                    100,
                    $score
                )
            ),
            2
        );


    imagedestroy(
        $reference
    );


    imagedestroy(
        $submitted
    );


    /*
    |--------------------------------------------------------------------------
    | Prototype labels
    |--------------------------------------------------------------------------
    */

    if (
        $score >= 75
    ) {

        $result =
            'High Similarity';

    } elseif (
        $score >= 50
    ) {

        $result =
            'Moderate Similarity';

    } else {

        $result =
            'Needs Manual Verification';
    }


    return [
        'success' =>
            true,

        'similarity' =>
            $score,

        'result' =>
            $result
    ];
}