<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class GoogleOAuthController extends AbstractController
{
    public function __construct(
        private readonly ClientRegistry $clientRegistry,
        #[Autowire('%env(GOOGLE_CLIENT_ID)%')]
        private readonly string $googleClientId,
        #[Autowire('%env(GOOGLE_CLIENT_SECRET)%')]
        private readonly string $googleClientSecret,
    ) {
    }

    #[Route(path: '/connect/google', name: 'connect_google')]
    public function connect(): RedirectResponse
    {
        if ('' === trim($this->googleClientId) || '' === trim($this->googleClientSecret)) {
            $this->addFlash(
                'error',
                'Google sign-in is not set up yet. Add GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET to .env or .env.local (Google Cloud Console → APIs & Services → Credentials → your OAuth 2.0 Client ID).',
            );

            return $this->redirectToRoute('app_login');
        }

        return $this->clientRegistry
            ->getClient('google')
            ->redirect(
                [],
                ['prompt' => 'select_account'],
            );
    }

    #[Route(path: '/connect/google/check', name: 'connect_google_check')]
    public function connectCheck(
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        Security $security,
        UserPasswordHasherInterface $passwordHasher,
        LoggerInterface $logger,
    ): Response {
        $this->configureCaBundleIfAvailable($logger);

        try {
            $googleUser = $this->clientRegistry->getClient('google')->fetchUser();
        } catch (IdentityProviderException $exception) {
            $logger->error('Google OAuth identity provider error.', [
                'message' => $exception->getMessage(),
                'exception_class' => $exception::class,
            ]);
            $this->addFlash('error', sprintf(
                'Google sign-in was rejected or failed. Please try again. (%s) [ini=%s | curl.cainfo=%s | openssl.cafile=%s]',
                $exception->getMessage(),
                (string) php_ini_loaded_file(),
                (string) ini_get('curl.cainfo'),
                (string) ini_get('openssl.cafile')
            ));

            return $this->redirectToRoute('app_login');
        } catch (\Throwable $exception) {
            $logger->error('Google OAuth unexpected error during fetchUser.', [
                'message' => $exception->getMessage(),
                'exception_class' => $exception::class,
            ]);
            $this->addFlash('error', sprintf(
                'Google sign-in could not be completed. Please try again. (%s) [ini=%s | curl.cainfo=%s | openssl.cafile=%s]',
                $exception->getMessage(),
                (string) php_ini_loaded_file(),
                (string) ini_get('curl.cainfo'),
                (string) ini_get('openssl.cafile')
            ));

            return $this->redirectToRoute('app_login');
        }

        $email = $googleUser->getEmail();
        if (!\is_string($email) || '' === trim($email)) {
            $this->addFlash('error', 'Your Google account did not provide an email address.');

            return $this->redirectToRoute('app_login');
        }

        $user = $userRepository->findOneByEmailIgnoreCase($email);
        if (null === $user) {
            $user = new User();
            $user->setEmail($email);
            $user->setUsername($this->buildUniqueUsername($email, $userRepository));
            $user->setRoles(['ROLE_STAFF']);
            $user->setIsVerified(true);
            $user->setVerificationToken(null);
            $user->setPassword($passwordHasher->hashPassword($user, bin2hex(random_bytes(32))));
            $entityManager->persist($user);
            $this->addFlash('success', 'Your account was created through Google sign-in.');
        }

        if (true !== $user->isVerified()) {
            $user->setIsVerified(true);
        }

        $entityManager->flush();

        $security->login($user, null, 'main');

        return $this->redirectToRoute($this->resolvePostLoginRoute($user));
    }

    private function buildUniqueUsername(string $email, UserRepository $userRepository): string
    {
        $emailLocalPart = (string) preg_replace('/@.*$/', '', trim($email));
        $base = (string) preg_replace('/[^a-z0-9._-]+/i', '', $emailLocalPart);
        $base = '' !== $base ? strtolower($base) : 'google_staff';

        $candidate = $base;
        $suffix = 1;
        while (null !== $userRepository->findOneBy(['username' => $candidate])) {
            ++$suffix;
            $candidate = sprintf('%s_%d', $base, $suffix);
        }

        return $candidate;
    }

    private function resolvePostLoginRoute(User $user): string
    {
        $roles = $user->getRoles();

        if (\in_array('ROLE_ADMIN', $roles, true)) {
            return 'app_dashboard';
        }

        if (\in_array('ROLE_STAFF', $roles, true)) {
            return 'app_staff_dashboard';
        }

        return 'app_home';
    }

    private function configureCaBundleIfAvailable(LoggerInterface $logger): void
    {
        $configuredPaths = array_filter([
            (string) ini_get('curl.cainfo'),
            (string) ini_get('openssl.cafile'),
            (string) getenv('CURL_CA_BUNDLE'),
            (string) getenv('SSL_CERT_FILE'),
        ]);

        foreach ($configuredPaths as $path) {
            if (is_file($path)) {
                return;
            }
        }

        $phpDir = \dirname(\PHP_BINARY);
        $candidatePaths = [
            $phpDir.'\\extras\\ssl\\cacert.pem',
            $phpDir.'\\extras\\ssl\\cert.pem',
            $phpDir.'\\cacert.pem',
            'C:\\xampp\\php\\extras\\ssl\\cacert.pem',
            'C:\\xampp\\apache\\bin\\curl-ca-bundle.crt',
        ];

        foreach ($candidatePaths as $candidatePath) {
            if (!is_file($candidatePath)) {
                continue;
            }

            @ini_set('curl.cainfo', $candidatePath);
            @ini_set('openssl.cafile', $candidatePath);
            putenv('CURL_CA_BUNDLE='.$candidatePath);
            putenv('SSL_CERT_FILE='.$candidatePath);

            $logger->info('Configured CA bundle for Google OAuth request.', [
                'ca_bundle' => $candidatePath,
            ]);

            return;
        }
    }
}
