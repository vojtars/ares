<?php

namespace Sunkaflek;

use Assert\Assertion;
use Sunkaflek\Justice\JusticeRecord;
use Sunkaflek\Justice\SubjectNotFoundException;
use Sunkaflek\Parser\JusticeJednatelPersonParser;
use Sunkaflek\Parser\JusticeSpolecnikPersonParser;
use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;

final class Justice
{

    /**
     * @var string
     */
    const URL_BASE = 'https://or.justice.cz/ias/ui/';

    /**
     * @var string
     */
    const URL_SUBJECTS = 'https://or.justice.cz/ias/ui/rejstrik-$firma?ico=%d';

    /**
     * @var Client
     */
    private $client;

    /**
     * Justice constructor.
     *
     * @param Client $client
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * @param string $id
     *
     * @return JusticeRecord|false
     * @throws SubjectNotFoundException
     *
     */
    public function findById($id)
    {
        Assertion::string($id);

        $crawler = $this->client->request('GET', sprintf(self::URL_SUBJECTS, $id));
        $detailUrl = $this->extractDetailUrlFromCrawler($crawler);

        if (false === $detailUrl) {
            return false;
        }

        $people = [];

        $crawler = $this->client->request('GET', $detailUrl);
        $crawler->filter('.aunp-content .div-table')->each(function (Crawler $table) use (&$people) {
            $title = $table->filter('.vr-hlavicka')->text();

            try {
                if ('jednatel: ' === $title) {
                    $person = JusticeJednatelPersonParser::parseFromDomCrawler($table);
                    $people[$person->getName()] = $person;
                } elseif ('Společník: ' === $title) {
                    $person = JusticeSpolecnikPersonParser::parseFromDomCrawler($table);
                    $people[$person->getName()] = $person;
                }
            } catch (\Exception $e) {
                throw $e;
            }
        });

        return new JusticeRecord($people);
    }

    /**
     * @param Crawler $crawler
     *
     * @return false|string
     */
    private function extractDetailUrlFromCrawler(Crawler $crawler)
    {
        $linksFound = $crawler->filter('.result-links > li > a');
        if (!$linksFound) {
            return false;
        }

        $href = $linksFound->extract(['href']);
        if (!isset($href[1])) {
            return false;
        }

        return self::URL_BASE . $href[1];
    }
}
