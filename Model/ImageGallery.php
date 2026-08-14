<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ImageGallery\Model;

use ImageGallery\ImageGallery as ImageGalleryModule;
use ImageGallery\Model\Base\ImageGallery as BaseImageGallery;

class ImageGallery extends BaseImageGallery
{
    /**
     * La colonne `file` est nullable (Propel genere donc `?string`), mais un slide
     * sans image n'a pas de sens cote lecture : le nom de fichier est expose comme
     * une chaine, les appelants (presenter, loop, templates) n'ont pas a gerer le
     * null. Utiliser parent::getFile() pour distinguer "absent" de "vide".
     *
     * Seule tolerance connue : l'API admin expose une chaine vide plutot que null
     * pour un slide sans image desktop. Sans consequence a l'execution (les
     * consommateurs testent la chaine vide), et le narrowing evite d'avoir a gerer
     * le null chez tous les appelants qui concatenent ce retour.
     */
    public function getFile(): string
    {
        return (string) parent::getFile();
    }

    /**
     * @return string the path to the upload directory where files are stored, without final slash
     */
    public function getUploadDir(): string {
        return ImageGalleryModule::getUploadDirPath();
    }

    // La visibilite est portee par la colonne `disable` (semantique inversee).
    public function setVisible(?int $visible = null): static
    {
        return $this->setDisable($visible ? 0 : 1);
    }

    /**
     * Publication rule shared by the presenter, the API filter (through
     * ImageGalleryQuery::filterByPublished) and the loop. Derived from
     * getPublicationStatus() so both never disagree.
     */
    public function isCurrentlyPublished(?\DateTimeInterface $now = null): bool
    {
        return $this->getPublicationStatus($now) === PublicationStatus::Online;
    }

    public function getPublicationStatus(?\DateTimeInterface $now = null): PublicationStatus
    {
        if ($this->getDisable()) {
            return PublicationStatus::Disabled;
        }

        if (!$this->getLimited()) {
            return PublicationStatus::Online;
        }

        $now ??= new \DateTime();
        $start = $this->getStartDate();
        $end = $this->getEndDate();

        // A limited slide with an incomplete window is never served by the front
        // (the window requires both dates): report it as scheduled, not online, so
        // the back-office badge matches what the front actually shows.
        if ($start === null || $end === null || $now < $start) {
            return PublicationStatus::Scheduled;
        }

        if ($now > $end) {
            return PublicationStatus::Expired;
        }

        return PublicationStatus::Online;
    }
}
