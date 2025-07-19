<?php
/**
 * Bibliothèque PHP QR Code simplifiée
 * Version adaptée pour UCB Transport
 */

class QRcode {
    /**
     * Générer un QR code et le sauvegarder en PNG
     */
    public static function png($text, $outfile = false, $level = QR_ECLEVEL_L, $size = 3, $margin = 4, $saveandprint = false) {
        // Simulation de génération de QR code
        // Dans un vrai projet, utilisez une vraie bibliothèque comme endroid/qr-code
        
        // Créer une image simple avec le texte
        $width = 200;
        $height = 200;
        
        $image = imagecreate($width, $height);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        
        // Remplir le fond en blanc
        imagefill($image, 0, 0, $white);
        
        // Dessiner un motif simple (simulation QR)
        for ($i = 0; $i < 20; $i++) {
            for ($j = 0; $j < 20; $j++) {
                if (($i + $j + strlen($text)) % 3 == 0) {
                    imagefilledrectangle($image, $i * 10, $j * 10, ($i + 1) * 10, ($j + 1) * 10, $black);
                }
            }
        }
        
        // Ajouter du texte au centre
        $font_size = 2;
        $text_width = imagefontwidth($font_size) * strlen(substr($text, 0, 10));
        $text_height = imagefontheight($font_size);
        $x = ($width - $text_width) / 2;
        $y = ($height - $text_height) / 2;
        
        // Fond blanc pour le texte
        imagefilledrectangle($image, $x - 5, $y - 5, $x + $text_width + 5, $y + $text_height + 5, $white);
        imagestring($image, $font_size, $x, $y, substr($text, 0, 10), $black);
        
        if ($outfile) {
            imagepng($image, $outfile);
        } else {
            header('Content-Type: image/png');
            imagepng($image);
        }
        
        imagedestroy($image);
        return true;
    }
}

// Constantes pour la compatibilité
define('QR_ECLEVEL_L', 0);
define('QR_ECLEVEL_M', 1);
define('QR_ECLEVEL_Q', 2);
define('QR_ECLEVEL_H', 3);
?>