<?php
namespace ERD\DoctrineHelpersBundle\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Bundle\DoctrineBundle\Command\DoctrineCommand;
use Symfony\Bundle\DoctrineBundle\Mapping\DisconnectedMetadataFactory;

class GenerateAccessorsForExistingClassesCommand extends DoctrineCommand
{
    protected function configure()
    {
        $this
            ->setName('erd:doctrine:generate:accessors')
            ->setAliases(array('generate:erd:doctrine:accessors'))
            ->setDescription('From existing annotation-mapped classes, generates method stubs in a separate trait that\'s then used in each class')
            ->addArgument('name', InputArgument::REQUIRED, 'A bundle name, a namespace, or a class name')
            ->addOption('no-backup', null, InputOption::VALUE_NONE, 'Do not backup existing entities files.')
            ->setHelp(<<<EOT
If you've already written a set of model classes and used annotations to map them,
the standard <info>doctrine:generate:entities</info> command is almost useless.
It recreates properties in each class that were already defined in parent class,
makes all properties private, leading to visibility conflicts, and generates
method stubs (getters, setters, adders, and removers) that are incomplete when it
comes to actually keeping the relationship up to date (e.g. updating both sides of
a bidirectional collection) and that are shown directly in your entity classes,
adding a lot of noise.

This command, by contrast, assumes that you know how to manage your properties and
annotations. All it does is, for every entity class provided, it looks at the
properties just declared locally in that class and generates accessor methods for
them. These accessors use the annotations to figure out what code they must include
to  properly maintain the relationships  (e.g. update both sides, etc). The accessors
are then stored in a separate trait (to not pollute your code) which is `use`d by the
entity class, allowing full ide autocompletion.


To specify which entities you want to create accessors for, use:

* For a bundle:

  <info>php app/console erd:doctrine:generate:accessors MyCustomBundle</info>

* For a single entity:

  <info>php app/console erd:doctrine:generate:accessors MyCustomBundle:User</info>
  <info>php app/console erd:doctrine:generate:accessors MyCustomBundle/Entity/User</info>

* To a namespace

  <info>php app/console erd:doctrine:generate:accessors MyCustomBundle/Entity</info>

Note that the entities must be in a bundle.

By default, the unmodified version of each entity is backed up and saved
(e.g. Product.php~). To prevent this task from creating the backup file,
pass the <comment>--no-backup</comment> option:

  <info>php app/console erd:doctrine:generate:accessors Blog/Entity --no-backup</info>
EOT
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $doctrine =  $this->getContainer()->get('doctrine');
        $manager = new DisconnectedMetadataFactory($doctrine);
        $metadatas = array();

        try {
            $bundle = $this->getApplication()->getKernel()->getBundle($input->getArgument('name'));

            $output->writeln(sprintf('Generating entities for bundle "<info>%s</info>"', $bundle->getName()));
            $metadata = $manager->getBundleMetadata($bundle);
        }
        catch (\InvalidArgumentException $e) {
            $name = strtr($input->getArgument('name'), '/', '\\');

            if (false !== $pos = strpos($name, ':')) {
                $bundleName = substr($name, 0, $pos);
            }
            else {
                $pos = strpos($name, '\\');
                $bundleName = substr($name, 0, $pos);
            }

            $name = $doctrine->getEntityNamespace($bundleName).'\\'.substr($name, $pos + 1);

            //have X/Y/Z at this point. X is just a vendor name (rather than a bundle alias) and no longer any colons.
            if (class_exists($name)) {
                $output->writeln(sprintf('Generating entity "<info>%s</info>"', $name));
                $metadata = $manager->getClassMetadata($name);
            } else {
                $output->writeln(sprintf('Generating entities for namespace "<info>%s</info>"', $name));
                $metadata = $manager->getNamespaceMetadata($name);
            }

            $bundle = $this->getApplication()->getKernel()->getBundle($bundleName);
        }

        $accessorsNS = $doctrine->getEntityNamespace($bundle->getName()).'\AutomaticAccessors';
        $backupExisting = !$input->getOption('no-backup');

        foreach ($metadata->getMetadata() as $m) {
            if ($backupExisting) {
                $basename = substr($m->name, strrpos($m->name, '\\') + 1);
                $output->writeln(sprintf('  > backing up <comment>%s.php</comment> to <comment>%s.php~</comment>', $basename, $basename));
            }
            // Getting the metadata for the entity class once more to get the correct path if the namespace has multiple occurrences
            try {
                $entityMetadata = $manager->getClassMetadata($m->getName());
            } catch (\RuntimeException $e) {
                // fall back to the bundle metadata when no entity class could be found
                $entityMetadata = $metadata;
            }

            $output->writeln(sprintf('  > generating <comment>%s</comment>', $m->name));

            $metadatas[] = $m;
        }


        $generator = new \ERD\DoctrineHelpersBundle\Tools\AccessorGenerator(new \Doctrine\Common\Annotations\AnnotationReader(), $metadatas, ['backupExisting'=>$backupExisting]);
        $generator->generate($entityMetadata->getPath(), $accessorsNS);
    }
}
