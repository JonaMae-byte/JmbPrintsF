<?php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\ActivityLog;
use App\Form\CategoryType;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/category')]
final class CategoryController extends AbstractController
{
    #[Route('/', name: 'app_category_index', methods: ['GET'])]
    public function index(CategoryRepository $categoryRepository): Response
    {
        return $this->render('category/index.html.twig', [
            'categories' => $categoryRepository->findBy([], ['id' => 'DESC']),
        ]);
    }

    #[Route('/new', name: 'app_category_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $category = new Category();
        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // persist category
            $entityManager->persist($category);

            // create activity log
            $log = new ActivityLog();
            $log->setAction('Create Category');
            $log->setDetails('Category created: ' . $category->getName() . ' (ID: ' . ($category->getId() ?? 'n/a') . ')');
            $log->setUser($this->getUser()?->getUserIdentifier() ?? 'Unknown');
            $log->setCreatedAt(new \DateTimeImmutable());
            $entityManager->persist($log);

            // single flush for both
            $entityManager->flush();

            $this->addFlash('success', '✅ Category created successfully.');
            return $this->redirectToRoute('app_category_index');
        }

        return $this->render('category/new.html.twig', [
            'category' => $category,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_category_show', methods: ['GET'])]
    public function show(Category $category): Response
    {
        return $this->render('category/show.html.twig', [
            'category' => $category,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_category_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Category $category, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        // Create delete form
        $deleteForm = $this->createFormBuilder()
            ->setAction($this->generateUrl('app_category_delete', ['id' => $category->getId()]))
            ->setMethod('POST')
            ->getForm();

        if ($form->isSubmitted() && $form->isValid()) {
            // category is managed; just flush after persisting log
            // create activity log
            $log = new ActivityLog();
            $log->setAction('Edit Category');
            $log->setDetails('Category updated: ' . $category->getName() . ' (ID: ' . $category->getId() . ')');
            $log->setUser($this->getUser()?->getUserIdentifier() ?? 'Unknown');
            $log->setCreatedAt(new \DateTimeImmutable());
            $entityManager->persist($log);

            $entityManager->flush();

            $this->addFlash('success', '✅ Category updated successfully.');
            return $this->redirectToRoute('app_category_index');
        }

        return $this->render('category/edit.html.twig', [
            'category' => $category,
            'form' => $form->createView(),
            'delete_form' => $deleteForm->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'app_category_delete', methods: ['POST'])]
    public function delete(Request $request, Category $category, EntityManagerInterface $entityManager): Response
    {
        // Validate CSRF token
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete'.$category->getId(), $token)) {
            $this->addFlash('error', '⚠️ Invalid CSRF token. Deletion failed.');
            return $this->redirectToRoute('app_category_index');
        }

        // Check if category has linked products
        if ($category->getProducts()->count() > 0) {
            $this->addFlash('error', '⚠️ Cannot delete this category because it still has products.');
            return $this->redirectToRoute('app_category_index');
        }

        // Create activity log BEFORE remove so we still have the category data
        $log = new ActivityLog();
        $log->setAction('Edit Category');
        $log->setDetails('Category updated: ' . $category->getName() . ' (ID: ' . $category->getId() . ')');
        $log->setUser($this->getUser()?->getUsername() ?? 'Unknown');
        $log->setCreatedAt(new \DateTimeImmutable());
        $entityManager->persist($log);
        $entityManager->flush();


        // remove and flush both log + removal in single transaction
        $entityManager->remove($category);
        $entityManager->flush();

        $this->addFlash('success', '✅ Category deleted successfully.');

        return $this->redirectToRoute('app_category_index');
    }
}
