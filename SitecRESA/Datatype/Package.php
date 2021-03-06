<?php
/**
 * User: patriceb
 * Date: 20/06/2016
 * Time: 10:08
 */

namespace SitecRESA\Datatype;

/**
 * @author Patrice BRUN <patrice.brun@sitec.fr>
 *
 * @property int    $id identifiant unique
 * @property string $libelle nom
 * @property string $description description
 * @property float  $prix prix
 * @property int    $jourArrivee jour d'arrivée 0|1|2|3|4|5|6
 * @property string $theme
 * @property array  $etapes liste des étapes
 * @property int    $nbNuits nombre de nuits
 * @property int    $nbJours nombre de jours
 * @property-read \SitecRESA\Datatype\AccesResolverList $galleriePhoto tableau de photos (SitecRESA\Datatype\Photo). (Accès WS)
 * @property-read \SitecRESA\Datatype\Photo $photo première photo
 * @property-read array $categorie liste des catégories associées à ce package
 */

class Package extends SavableDatatypeAbstract implements Fetchable{

    protected $_id;
    protected $_libelle;
    protected $_description;
    protected $_prix;
    protected $_jourArrivee;
    protected $_theme;
    protected $_etapes;
    protected $_nbNuits;
    protected $_nbJours;
    protected $_galleriePhoto;
    protected $_photo;
    protected $_categorie;
    /**
     *
     * @param  \SitecRESA\WS\Client $apiClient
     * @param  int $id
     * @return self
     */
    public static function fetch(\SitecRESA\WS\Client $apiClient, $id) {
        return $apiClient->package("get",array("idRessource"=> $id));
    }

    public function save() {

    }

    public function toArray() {

    }

}