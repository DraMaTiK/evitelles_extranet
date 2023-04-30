<?php

namespace App\Controller;

use App\Entity\Sepas;
use App\Form\SepasType;
use App\Repository\SepasRepository;
use App\Service\WorldlineService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/sepas')]
class SepasController extends AbstractController
{
    #[Route('/', name: 'app_sepas_index', methods: ['GET'])]
    public function index(SepasRepository $sepasRepository): Response
    {
        return $this->render('sepas/index.html.twig', [
            'sepas' => $sepasRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_sepas_new', methods: ['GET', 'POST'])]
    public function new(Request $request, SepasRepository $sepasRepository): Response
    {
        $sepa = new Sepas();
        $form = $this->createForm(SepasType::class, $sepa, ['mode' => 'create']);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $sepasRepository->save($sepa, true);

            return $this->redirectToRoute('app_sepas_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('sepas/new.html.twig', [
            'sepa' => $sepa,
            'form' => $form,
        ]);
    }

    #[Route('/result', name: 'app_sepas_result', methods: ['GET'])]
    public function result(): Response
    {
        return $this->render('sepas/result.html.twig');
    }

    #[Route('/{token}', name: 'app_sepas_show', methods: ['GET', 'POST'])]
    public function show($token, SepasRepository $sepasRepository, WorldLineService $worldLineService, Request $request): Response
    {
        $sepa = $sepasRepository->findOneBy(['token' => $token]);

        if(!$sepa instanceof Sepas) {
            return $this->redirectToRoute('homepage');
        }

        $form = $this->createForm(SepasType::class, $sepa, ['mode' => 'view']);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $sepasRepository->save($sepa, true);
            $payment = $worldLineService->make($sepa);

            if(isset($payment['errors'])) {
                foreach($payment['errors'] as $error) {
                    $this->addFlash('error', $error->getMessage());
                }
            } else {
                $this->addFlash('success', "Votre autorisation de prélèvement a été réalisée avec succès.");
            }

            return $this->redirectToRoute('app_sepas_result', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('sepas/show.html.twig', [
            'sepa' => $sepa,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_sepas_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Sepas $sepa, SepasRepository $sepasRepository): Response
    {
        $form = $this->createForm(SepasType::class, $sepa, ['mode' => 'edit']);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $sepasRepository->save($sepa, true);

            return $this->redirectToRoute('app_sepas_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('sepas/edit.html.twig', [
            'sepa' => $sepa,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_sepas_delete', methods: ['POST'])]
    public function delete(Request $request, Sepas $sepa, SepasRepository $sepasRepository): Response
    {
        if ($this->isCsrfTokenValid('delete'.$sepa->getId(), $request->request->get('_token'))) {
            $sepasRepository->remove($sepa, true);
        }

        return $this->redirectToRoute('app_sepas_index', [], Response::HTTP_SEE_OTHER);
    }
}
