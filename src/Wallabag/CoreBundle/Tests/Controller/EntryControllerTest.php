<?php

namespace Wallabag\CoreBundle\Tests\Controller;

use Wallabag\CoreBundle\Tests\WallabagCoreTestCase;
use Doctrine\ORM\AbstractQuery;

class EntryControllerTest extends WallabagCoreTestCase
{
    public function testLogin()
    {
        $client = $this->getClient();

        $client->request('GET', '/new');

        $this->assertEquals(302, $client->getResponse()->getStatusCode());
        $this->assertContains('login', $client->getResponse()->headers->get('location'));
    }

    public function testGetNew()
    {
        $this->logInAs('admin');
        $client = $this->getClient();

        $crawler = $client->request('GET', '/new');

        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        $this->assertCount(1, $crawler->filter('input[type=url]'));
        $this->assertCount(1, $crawler->filter('button[type=submit]'));
    }

    public function testPostNewEmpty()
    {
        $this->logInAs('admin');
        $client = $this->getClient();

        $crawler = $client->request('GET', '/new');

        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        $form = $crawler->filter('button[type=submit]')->form();

        $crawler = $client->submit($form);

        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertCount(1, $alert = $crawler->filter('form ul li')->extract(array('_text')));
        $this->assertEquals('This value should not be blank.', $alert[0]);
    }

    public function testPostNewOk()
    {
        $this->logInAs('admin');
        $client = $this->getClient();

        $crawler = $client->request('GET', '/new');

        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        $form = $crawler->filter('button[type=submit]')->form();

        $data = array(
            'entry[url]' => 'http://www.lemonde.fr/pixels/article/2015/03/28/plongee-dans-l-univers-d-ingress-le-jeu-de-google-aux-frontieres-du-reel_4601155_4408996.html',
        );

        $client->submit($form, $data);

        $this->assertEquals(302, $client->getResponse()->getStatusCode());

        $crawler = $client->followRedirect();

        $this->assertGreaterThan(1, $alert = $crawler->filter('h2 a')->extract(array('_text')));
        $this->assertContains('Google', $alert[0]);
    }

    public function testArchive()
    {
        $this->logInAs('admin');
        $client = $this->getClient();

        $client->request('GET', '/archive/list');

        $this->assertEquals(200, $client->getResponse()->getStatusCode());
    }

    public function testStarred()
    {
        $this->logInAs('admin');
        $client = $this->getClient();

        $client->request('GET', '/starred/list');

        $this->assertEquals(200, $client->getResponse()->getStatusCode());
    }

    public function testView()
    {
        $this->logInAs('admin');
        $client = $this->getClient();

        $content = $client->getContainer()
            ->get('doctrine.orm.entity_manager')
            ->getRepository('WallabagCoreBundle:Entry')
            ->findOneByIsArchived(false);

        $client->request('GET', '/view/'.$content->getId());

        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertContains($content->getTitle(), $client->getResponse()->getContent());
    }

    public function testEdit()
    {
        $this->logInAs('admin');
        $client = $this->getClient();

        $content = $client->getContainer()
            ->get('doctrine.orm.entity_manager')
            ->getRepository('WallabagCoreBundle:Entry')
            ->findOneByIsArchived(false);

        $crawler = $client->request('GET', '/edit/'.$content->getId());

        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        $this->assertCount(1, $crawler->filter('input[id=entry_title]'));
        $this->assertCount(1, $crawler->filter('button[id=entry_save]'));
    }

    public function testEditUpdate()
    {
        $this->logInAs('admin');
        $client = $this->getClient();

        $content = $client->getContainer()
            ->get('doctrine.orm.entity_manager')
            ->getRepository('WallabagCoreBundle:Entry')
            ->findOneByIsArchived(false);

        $crawler = $client->request('GET', '/edit/'.$content->getId());

        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        $form = $crawler->filter('button[type=submit]')->form();

        $data = array(
            'entry[title]' => 'My updated title hehe :)',
        );

        $client->submit($form, $data);

        $this->assertEquals(302, $client->getResponse()->getStatusCode());

        $crawler = $client->followRedirect();

        $this->assertGreaterThan(1, $alert = $crawler->filter('div[id=article] h1')->extract(array('_text')));
        $this->assertContains('My updated title hehe :)', $alert[0]);
    }

    public function testToggleArchive()
    {
        $this->logInAs('admin');
        $client = $this->getClient();

        $content = $client->getContainer()
            ->get('doctrine.orm.entity_manager')
            ->getRepository('WallabagCoreBundle:Entry')
            ->findOneByIsArchived(false);

        $client->request('GET', '/archive/'.$content->getId());

        $this->assertEquals(302, $client->getResponse()->getStatusCode());

        $res = $client->getContainer()
            ->get('doctrine.orm.entity_manager')
            ->getRepository('WallabagCoreBundle:Entry')
            ->findOneById($content->getId());

        $this->assertEquals($res->isArchived(), true);
    }

    public function testToggleStar()
    {
        $this->logInAs('admin');
        $client = $this->getClient();

        $content = $client->getContainer()
            ->get('doctrine.orm.entity_manager')
            ->getRepository('WallabagCoreBundle:Entry')
            ->findOneByIsStarred(false);

        $client->request('GET', '/star/'.$content->getId());

        $this->assertEquals(302, $client->getResponse()->getStatusCode());

        $res = $client->getContainer()
            ->get('doctrine.orm.entity_manager')
            ->getRepository('WallabagCoreBundle:Entry')
            ->findOneById($content->getId());

        $this->assertEquals($res->isStarred(), true);
    }

    public function testDelete()
    {
        $this->logInAs('admin');
        $client = $this->getClient();

        $content = $client->getContainer()
            ->get('doctrine.orm.entity_manager')
            ->getRepository('WallabagCoreBundle:Entry')
            ->findOneById(1);

        $client->request('GET', '/delete/'.$content->getId());

        $this->assertEquals(302, $client->getResponse()->getStatusCode());

        $client->request('GET', '/delete/'.$content->getId());

        $this->assertEquals(404, $client->getResponse()->getStatusCode());
    }

    public function testViewOtherUserEntry()
    {
        $this->logInAs('bob');
        $client = $this->getClient();

        $content = $client->getContainer()
            ->get('doctrine.orm.entity_manager')
            ->getRepository('WallabagCoreBundle:Entry')
            ->createQueryBuilder('e')
            ->select('e.id')
            ->leftJoin('e.user', 'u')
            ->where('u.username != :username')->setParameter('username', 'bob')
            ->setMaxResults(1)
            ->getQuery()
            ->getSingleResult(AbstractQuery::HYDRATE_ARRAY);

        $client->request('GET', '/view/'.$content['id']);

        $this->assertEquals(403, $client->getResponse()->getStatusCode());
    }

    public function testFilterOnUnreadeView()
    {
        $this->logInAs('admin');
        $client = $this->getClient();

        $crawler = $client->request('GET', '/unread/list');

        $form = $crawler->filter('button[id=submit-filter]')->form();

        $data = array(
            'entry_filter[readingTime][right_number]' => 11,
            'entry_filter[readingTime][left_number]' => 11
        );

        $crawler = $client->submit($form, $data);

        $this->assertCount(1, $crawler->filter('div[class=entry]'));
    }

    public function testFilterOnCreationDate()
    {
        $this->logInAs('admin');
        $client = $this->getClient();
        $crawler = $client->request('GET', '/unread/list');

        $form = $crawler->filter('button[id=submit-filter]')->form();
        $data = array(
            'entry_filter[createdAt][left_date]' => date('d/m/Y')
        );

        $crawler = $client->submit($form, $data);
        $this->assertCount(4, $crawler->filter('div[class=entry]'));

        $form = $crawler->filter('button[id=submit-filter]')->form();
        $data = array(
            'entry_filter[createdAt][right_date]' => date('d/m/Y')
        );
        $crawler = $client->submit($form, $data);
        $this->assertCount(0, $crawler->filter('div[class=entry]'));
    }
}
