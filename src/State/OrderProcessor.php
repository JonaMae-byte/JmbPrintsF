<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Doctrine\Common\State\PersistProcessor;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Order;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class OrderProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: PersistProcessor::class)]
        private readonly ProcessorInterface $persistProcessor,
        private readonly Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof Order && $operation instanceof Post) {
            $user = $this->security->getUser();
            if ($user instanceof User) {
                $data->setUser($user);
            }

            if (null === $data->getStatus() || '' === $data->getStatus()) {
                $data->setStatus('Pending');
            }

            if ($data->getOrderItems()->isEmpty()) {
                throw new BadRequestHttpException('Order must contain at least one line item.');
            }

            foreach ($data->getOrderItems() as $item) {
                $product = $item->getProduct();
                if (null !== $product) {
                    $price = (float) $product->getPrice();
                    $item->setPrice($price);
                    $item->setSubtotal($price * (int) $item->getQuantity());
                }
            }
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
