<?php

namespace App\Controller;

use App\Service\CloudinaryService;
use App\Service\VoyageImageService;
use App\Service\ValidationService;
use App\Repository\VoyageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ImageController extends AbstractController
{
    public function __construct(
        private readonly VoyageImageService $voyageImageService,
        private readonly VoyageRepository $voyageRepository,
        private readonly AdminController $adminController,
        private readonly ValidationService $validationService,
        private readonly CloudinaryService $cloudinaryService
    ) {
    }

    // ==================== ADMIN IMAGES ====================

    #[Route('/admin/images', name: 'admin_images', methods: ['GET'])]
    public function adminImages(Request $request): Response
    {
        if ($this->adminController->ensureIsAdmin($request) !== null) {
            return $this->adminController->ensureIsAdmin($request);
        }

        $images = $this->voyageImageService->getAllImagesForAdmin();
        return $this->render('admin/images.html.twig', [
            'images' => $images,
        ]);
    }

    #[Route('/admin/images/new', name: 'admin_image_new', methods: ['GET', 'POST'])]
    public function adminNewImage(Request $request): Response
    {
        if ($this->adminController->ensureIsAdmin($request) !== null) {
            return $this->adminController->ensureIsAdmin($request);
        }

        if ($request->isMethod('POST')) {
            $data = $request->request->all();

            // Use ValidationService for validation
            $this->validationService->clearErrors();
            $this->validationService->validateRequired($data, ['voyage_id', 'image_url']);
            $this->validationService->validateNumber($data['voyage_id'] ?? '', 'voyage_id', 1);
            $this->validationService->validateString(is_string($data['image_url'] ?? '') ? ($data['image_url'] ?? '') : '', 'image_url', 5, 500);

            if (!$this->validationService->isValid()) {
                $errors = $this->validationService->getErrors();
                foreach ($errors as $field => $fieldErrors) {
                    foreach ($fieldErrors as $error) {
                        $this->addFlash('error', $error);
                    }
                }
                return $this->render('admin/image_form.html.twig', [
                    'image' => $data,
                    'voyages' => $this->voyageRepository->findAll(),
                    'errors' => $errors,
                ]);
            }
unset($data['id']);
            $image = $this->voyageImageService->createVoyageImage($data);
            if ($image) {
                $this->addFlash('success', 'Image created successfully!');
            } else {
                $this->addFlash('error', 'Failed to create image. Please select a valid voyage.');
            }
            return $this->redirectToRoute('admin_images');
        }

        return $this->render('admin/image_form.html.twig', [
            'image' => null,
            'voyages' => $this->voyageRepository->findAll(),
        ]);
    }

    #[Route('/admin/images/cloudinary-upload', name: 'admin_cloudinary_upload', methods: ['GET', 'POST'])]
    public function adminCloudinaryUpload(Request $request): Response
    {
        if ($this->adminController->ensureIsAdmin($request) !== null) {
            return $this->adminController->ensureIsAdmin($request);
        }

        $voyages = $this->voyageRepository->findAll();
        $selectedVoyageId = $request->query->getInt('voyage_id', 0);
        $data = [];
        if ($selectedVoyageId > 0) {
            $data['voyage_id'] = $selectedVoyageId;
        }

        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            if (empty($data['voyage_id']) && $selectedVoyageId > 0) {
                $data['voyage_id'] = $selectedVoyageId;
            }
            $file = $request->files->get('image');

            $this->validationService->clearErrors();
            $this->validationService->validateRequired($data, ['voyage_id']);
            $this->validationService->validateNumber($data['voyage_id'] ?? '', 'voyage_id', 1);
            if (!$file instanceof UploadedFile) {
                $this->validationService->validateCustom(false, fn($value) => $value === true, 'Please select an image file to upload.', 'image');
            }

            if (!$this->validationService->isValid()) {
                $errors = $this->validationService->getErrors();
                foreach ($errors as $fieldErrors) {
                    foreach ($fieldErrors as $error) {
                        $this->addFlash('error', $error);
                    }
                }

                return $this->render('admin/cloudinary_upload.html.twig', [
                    'voyages' => $voyages,
                    'data' => $data,
                    'errors' => $errors,
                    'selectedVoyageId' => $selectedVoyageId,
                ]);
            }

            try {
                $pubId = $data['cloudinary_public_id'] ?? null;
                $uploadResult = $this->cloudinaryService->uploadImageFile(
                    $file,
                    is_string($pubId) ? $pubId : null,
                    'voyage_images'
                );
            } catch (\Throwable $e) {
                $this->addFlash('error', 'Cloudinary upload failed: ' . $e->getMessage());

                return $this->render('admin/cloudinary_upload.html.twig', [
                    'voyages' => $voyages,
                    'data' => $data,
                    'errors' => ['cloudinary' => [$e->getMessage()]],
                    'selectedVoyageId' => $selectedVoyageId,
                ]);
            }

            $imageData = [
                'voyage_id' => (int) $data['voyage_id'],
                'image_url' => $uploadResult['secure_url'] ?? $uploadResult['url'] ?? '',
                'cloudinary_public_id' => $uploadResult['public_id'] ?? '',
            ];

            $image = $this->voyageImageService->createVoyageImage($imageData);
            if ($image) {
                $this->addFlash('success', 'Image uploaded to Cloudinary and saved successfully!');
                return $this->redirectToRoute('admin_images');
            }

            $this->addFlash('error', 'Uploaded successfully, but saving the image record failed.');
        }

        return $this->render('admin/cloudinary_upload.html.twig', [
            'voyages' => $voyages,
            'data' => $data,
            'errors' => [],
            'selectedVoyageId' => $selectedVoyageId,
        ]);
    }

    #[Route('/admin/images/{id}/edit', name: 'admin_image_edit', methods: ['GET', 'POST'])]
    public function adminEditImage(Request $request, int $id): Response
    {
        if ($this->adminController->ensureIsAdmin($request) !== null) {
            return $this->adminController->ensureIsAdmin($request);
        }

        $image = $this->voyageImageService->getImageByIdForAdmin($id);
        if (!$image) {
            throw $this->createNotFoundException('Image not found');
        }

        if ($request->isMethod('POST')) {
            $data = $request->request->all();

            $this->voyageImageService->updateVoyageImage($id, $data);
            $this->addFlash('success', 'Image updated successfully!');
            return $this->redirectToRoute('admin_images');
        }

        return $this->render('admin/image_form.html.twig', [
            'image' => $image,
            'voyages' => $this->voyageRepository->findAll(),
        ]);
    }

    #[Route('/admin/images/{id}/delete', name: 'admin_image_delete', methods: ['GET', 'POST'])]
    public function adminDeleteImage(Request $request, int $id): Response
    {
        if ($this->adminController->ensureIsAdmin($request) !== null) {
            return $this->adminController->ensureIsAdmin($request);
        }

        $this->voyageImageService->deleteVoyageImage($id);
        $this->addFlash('success', 'Image deleted successfully!');
        return $this->redirectToRoute('admin_images');
    }
}