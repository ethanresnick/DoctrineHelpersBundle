<?php
namespace ERD\DoctrineHelpersBundle\Tools;

use Doctrine\ORM\Mapping\ClassMetadataInfo,
    Doctrine\Common\Util\Inflector,
    Doctrine\DBAL\Types\Type,
    ERD\AnnotationHelpers\PowerReader,
    ERD\DoctrineHelpersBundle\Tools\Annotation\AccessorSettings;

/**
 * Generic class used to generate PHP5 entity stub methods from ClassMetadataInfo instances
 *
 * Largely duplicated from (but then highly simplified to remove unneeded options)
 * the generator in \Doctrine\Tools, because I couldn't extend that as it used `private`.
 */
class AccessorGenerator
{
    /**
     * @var bool Whether to back up existing entity classes. Accessor traits are never backed up.
     */
    protected $backupExisting = true;

    /**
     * @var string The extension to use for written php files
     */
    protected $extension = '.php';

    /**
     * @var string The actual spaces to use for indenting pieces of the generated code.
     */
    protected $spaces = '    ';

    /**
     * @var array Hash-map for handle types
     */
    protected $typeAlias = array(
        Type::DATETIMETZ => '\DateTime',
        Type::DATETIME => '\DateTime',
        Type::DATE => '\DateTime',
        Type::TIME => '\DateTime',
        Type::OBJECT => '\stdClass',
        Type::BIGINT => 'integer',
        Type::SMALLINT => 'integer',
        Type::TEXT => 'string',
        Type::DECIMAL => 'float',
    );

    /**
     * @var array Stores info about methods we've added directly to files, that Reflection wouldn't be aware of
     */
    protected $staticReflection = array();

    /**
     * @var string The FCQN of the annoation class storing settings information
     */
    protected $annotationClass = 'ERD\DoctrineHelpersBundle\Tools\Annotation\AccessorSettings';

    /**
     * @var PowerReader
     */
    protected $reader;

    /**
     * @var array[ClassMetadataInfo]
     */
    protected $metadatas;


    /**
     * @var string
     */
    protected static $getMethodTemplate =
        '/**
 * <description>
 *
 * @return <variableType>
 */
public function <methodName>()
{
<spaces>return $this-><fieldName>;
}';

    /**
     * @var string
     */
    protected static $setMethodTemplate =
        '/**
 * <description>
 *
 * @param <variableType>$<variableName>
 * @return <entity>
 */
public function <methodName>(<methodTypeHint>$<variableName><variableDefault>)
{
<spaces>$this-><fieldName> = $<variableName>;

<spaces>return $this;
}';

    /**
     * @var string
     */
    protected static $addMethodTemplate =
        '/**
 * <description>
 *
 * @param <variableType>$<variableName>
 * @return <entity>
 */
public function <methodName>(<methodTypeHint>$<variableName>)
{
<spaces>$this-><fieldName>[] = $<variableName>;

<spaces>return $this;
}';

    /**
     * @var string
     */
    protected static $removeMethodTemplate =
        '/**
 * <description>
 *
 * @param <variableType>$<variableName>
 */
public function <methodName>(<methodTypeHint>$<variableName>)
{
<spaces>$this-><fieldName>->removeElement($<variableName>);
}';

    protected static $biDirectionalManyToManyMethodTemplate = '/**
 * <description>
 *
 * @param <variableType>$<variableName>
 */
public function <methodName>(<methodTypeHint>$<variableName>)
{
<spaces>$this-><mode>BothCollections($<variableName>, $this-><fieldName>, \'<otherSideMethod(s)>\');
}';

    protected static $traitHeaderTemplate =
        '<?php

namespace <namespace>;

use Doctrine\ORM\Mapping as ORM;

/**
 * This trait was automatically generated by the erd:doctrine:generate:accessors command and will be overwritten
 * when the command is next run. Please don\'t make changes here directly. If you wish to alter a method in this
 * trait, simply define the method in your class directly as you want it, and then re-run the command: the version
 * of the method in this trait will be removed.
 */
trait <traitName>
{
<body>
}
';
    protected static $useTraitTemplate = '
<spaces>use \\<name>;
';

    /**
     * @param \Doctrine\Common\Annotations\Reader $reader The reader that will be used to process settings annotations
     * about how the accessors are to be generated.
     * @param array[ClassMetadataInfo] $metadatas The collection of ClassMetadataInfo instances to generate accessors for.
     * @param array An associative array of settings. Allowed keys are 'numSpaces', 'extension', and 'backupExisting'.
     */
    public function __construct(PowerReader $reader, array $metadatas, array $settings)
    {
        $this->reader = $reader;
        $this->metadatas = $metadatas;

        $this->updateSettings($settings);
    }

    /**
     * Generate and write entity classes for the given array of ClassMetadataInfo instances
     *
     * @param string $outputDirectory
     * @param string $accessorsNS The namespace for the generated accessors
     * @param boolean $unlinkOnly Whether to unlink previously-generated accessor traits from
     * their entities without replacing them with new ones.
     * @return void
     */
    public function generate($outputDirectory, $accessorsNS, $unlinkOnly = false)
    {
        $accessorsDir = $outputDirectory . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $accessorsNS);
        foreach ($this->metadatas as $metadata) {

            $accessorsTraitName = ($accessorsNS . '\\' . join('', array_slice(explode('\\', $metadata->name), -1)));
            $entityPath = $outputDirectory.'/'.str_replace('\\', DIRECTORY_SEPARATOR, $metadata->name).$this->extension;


            if ($this->backupExisting && file_exists($entityPath)) {
                $backupPath = dirname($entityPath) . DIRECTORY_SEPARATOR . basename($entityPath) . "~";
                if (!copy($entityPath, $backupPath)) {
                    throw new \RuntimeException("Attempted to backup old entity file but copy operation failed.");
                }
            }

            //have to remove the trait first so that the new accessor trait doesn't see
            //methods from the old accessor trait as being defined.
            $this->removeAccessorTraitFromEntityClass($entityPath, $accessorsTraitName);
            if(!$unlinkOnly)
            {
                $this->writeAccessorTrait($metadata, $accessorsDir, $accessorsNS);
                $this->addAccessorTraitToEntityClass($metadata, $entityPath, $accessorsTraitName);
            }
        }
    }

    public function removeAccessorTraitFromEntityClass($path, $accessorsTraitName)
    {
        if (file_exists($path)) {
            $currentCode = file_get_contents($path);
            $useStatement = str_replace(['<spaces>','<name>'], [$this->spaces, $accessorsTraitName], self::$useTraitTemplate);
            $newCode     = str_replace($useStatement, '', $currentCode);

            file_put_contents($path, $newCode);
            $this->parseTokensInEntityFile($newCode);
        }
        else {
            throw new \InvalidArgumentException("The entity you're trying to remove an accessors trait from doesn't exist.");
        }
    }

    public function addAccessorTraitToEntityClass(ClassMetadataInfo $metadata, $path, $accessorsTraitName)
    {
        if (file_exists($path)) {
            $currentCode = file_get_contents($path);
        } else {
            throw new \InvalidArgumentException("The entity you're trying to add an accessors trait to doesn't exist.");
        }

        //Do the updating
        $useStatement = str_replace(['<spaces>','<name>'], [$this->spaces, $accessorsTraitName], self::$useTraitTemplate);
        $codeToAdd = '';

        if (strpos($currentCode, $useStatement)===false) {
            $codeToAdd = $useStatement;
        }

        $newCode = substr($currentCode, 0, strrpos($currentCode, '}')) . $codeToAdd . "}";
        file_put_contents($path, $newCode);
    }

    public function writeAccessorTrait(ClassMetadataInfo $metadata, $accessorsDir, $accessorsNS)
    {
        if (!is_dir($accessorsDir)) {
            mkdir($accessorsDir, 0777, true);
        }

        $traitName = join('', array_slice(explode('\\', $metadata->name), -1));
        $path = $accessorsDir . '/' . $traitName . $this->extension;

        //Create the trait
        $accessors = $this->generateAccessorMethods($metadata);
        $code =  str_replace(
            ['<namespace>', '<traitName>', '<body>'],
            [$accessorsNS, $traitName, $accessors],
            self::$traitHeaderTemplate
        );
        file_put_contents($path, $code);
    }

    protected function generateAccessorMethods(ClassMetadataInfo $metadata)
    {
        //set up the mappings & annotation processor.
        $fieldMappings = $metadata->fieldMappings;
        $associationMappings = $metadata->associationMappings;
        $methods = array();

        if ($metadata->isMappedSuperclass)
        {
            $mappings = $this->getMappingsForMappedSuperClass($metadata);
            $fieldMappings = $mappings['fieldMappings'];
            $associationMappings = $mappings['associationMappings'];
        }

        foreach (["field"=>$fieldMappings, "association"=>$associationMappings] as $mappingType=>$mappings)
        {
            foreach($mappings as $mapping)
            {
                $settings = $this->getSettingsForField($mapping, ($mappingType=='association'), $metadata);

                //create the methods (setters and getters only)
                foreach($settings['validAccessors'] as $accessorType)
                {
                    $methodName = $this->getMethodNameForField($accessorType, $settings['publicName'], $settings['singular']);
                    $typeHint = (($accessorType=='get' || $accessorType=='set') && $settings['storesCollection']) ?
                                'Doctrine\Common\Collections\Collection' : $settings['defaultTypeHint'];

                    $code = $this->generateAccessorMethod(
                        $metadata, $accessorType, $methodName, $mapping['fieldName'],
                        $typeHint, ($settings['nullable'] ? 'null' : null), $settings['publicName'],
                        $settings['singular'], $settings['otherSideMethodEnding']
                    );

                    if($code) { $methods[] = $code; }
                }
            }
        }

        foreach ($methods as &$method) {
            $method = str_replace('<spaces>', $this->spaces, $method);
        }

        $methods = implode("\n\n", $methods);

        //for simplicity's sake, just always dump in this trait. rather than only on
        //($mapping['type']==ClassMetadataInfo::MANY_TO_MANY && (isset($mapping['mappedBy']) || isset($mapping['inversedBy']))
        $methodDeps = $this->spaces."use \\ERD\\DoctrineHelpersBundle\\Traits\\HasBidirectionalManyToMany;\n\n";

        return $methodDeps.$methods;
    }

    protected function generateAccessorMethod(ClassMetadataInfo $metadata, $type, $methodName, $fieldName, $typeHint = null, $defaultValue = null, $publicName = null, $singular = null, $otherSideMethodEnding = null)
    {
        if($this->hasMethod($methodName, $metadata->name))
        {
            return;
        }

        $useSingular = in_array($type, ["add", "remove"]);
        $this->staticReflection[$metadata->name]['methods'][] = $methodName;

        if(!$otherSideMethodEnding || $type=='get') {
            $var = sprintf('%sMethodTemplate', $type);
            $template = self::$$var;
        }
        else {
            $template = self::$biDirectionalManyToManyMethodTemplate;
        }

        $types = Type::getTypesMap();
        $variableTypePrefix = !isset($types[$typeHint]) ? '\\' : ''; //we're dealing with an object variable type.
        $variableType = $typeHint ? $variableTypePrefix.$this->getType($typeHint) . ' ' : null;
        $methodTypeHint = $typeHint && !isset($types[$typeHint]) ? '\\' . $typeHint . ' ' : null;
        $otherSideMethodEnding = Inflector::classify($otherSideMethodEnding);
        $bidirectionalSetMethods = "remove".$otherSideMethodEnding."', 'add".$otherSideMethodEnding;

        $replacements = array(
            '<description>' => ucfirst($type) . ' ' . ($useSingular ? $singular : $publicName),
            '<methodTypeHint>' => $methodTypeHint,
            '<variableType>' => $variableType,
            '<variableName>' => ($useSingular) ? Inflector::camelize($singular) : Inflector::camelize($publicName),
            '<methodName>' => $methodName,
            '<fieldName>' => $fieldName,
            '<variableDefault>' => ($defaultValue !== null) ? (' = ' . $defaultValue) : '',
            '<entity>' => $this->getClassName($metadata->name),
            '<mode>'   => ($type=='remove') ? 'removeFrom' : ($type=='set' ? 'setIn' : 'addTo'),
            '<otherSideMethod(s)>' => ($type=='set') ? $bidirectionalSetMethods : $type.$otherSideMethodEnding
        );

        $method = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $template
        );

        return $this->prefixCodeWithSpaces($method);
    }

    protected function getMethodNameForField($type, $publicName, $singular)
    {
        $useSingular = in_array($type, ["add", "remove"]);

        if ($useSingular) {
            $methodName = $type . Inflector::classify($singular);
        } else {
            $methodName = $type . Inflector::classify($publicName);
        }

        return $methodName;
    }
    /**
     * Takes the mapping information associated with a given field and returns some basic settings about its accessor generation.
     */
    protected function getSettingsForField($mapping, $isAssociation, $metadata)
    {
        //initialize some helpful variables
        $fieldName          = $mapping['fieldName'];
        $storesCollection   = ($isAssociation && ($mapping['type'] & ClassMetadataInfo::TO_MANY));
        $annotationClass    = $this->annotationClass; //bc PHP can't parse $this->x::PUBLIC_CONSTANT.
        $reflProp           = new \ReflectionProperty($metadata->name, $fieldName);
        $declaringReflClass = $reflProp->getDeclaringClass();
        $localReflClass     = new \ReflectionClass($metadata->name);

        //get the merged annotation for this property from the class in which it was declared
        $fieldAnnotations = $this->reader->getPropertyAnnotationsFromClass($reflProp, $declaringReflClass, $annotationClass);
        $fieldAnnotation  = new AccessorSettings(array());
        array_walk($fieldAnnotations, function($a) use ($fieldAnnotation) { $fieldAnnotation->mergeIn($a); });

        //set up settings defaults
        $singularDefault = self::singularify($fieldName);
        $defaults = [
            'visibility'           => $annotationClass::VISIBILITY_PUBLIC,
            'otherSideMethodEnding'=> null,
            'publicName'           => $fieldName,
            'nullable'             => false,
            'defaultTypeHint'      => $mapping['type'],
            'singular'             => is_array($singularDefault) ? $singularDefault[0] : $singularDefault
        ];

        //using the getSetting() helper closure, merge the settings from the annotation with the defaults
        //into a final $settings array, which we'll return.
        $getSetting = function($name, $annotation, $condition = null, $successValue = null, $default = null) use ($defaults)
        {
            $condition = ($condition==null) ? ($annotation!==null && isset($annotation->{$name})) : $condition;

            return ($condition) ? ($successValue ?: $annotation->{$name}) : ($default ?: $defaults[$name]);
        };

        $settings = [
            //direct settings read from the annotation
            'visibility'            => $getSetting('visibility', $fieldAnnotation),
            'otherSideMethodEnding' => $getSetting('otherSideMethodEnding', $fieldAnnotation),
            'publicName'            => $getSetting('publicName', $fieldAnnotation),
            'singular'              => $getSetting('singular', $fieldAnnotation),

            //settings dependent on whether we have an association/collection
            'storesCollection'      => $storesCollection,
            'nullable'              => ($isAssociation) ? (!$storesCollection && $this->isAssociationIsNullable($mapping)) : $defaults['nullable'],
            'defaultTypeHint'       => ($isAssociation) ? $mapping['targetEntity'] : $defaults['defaultTypeHint'],

            //settings we'll generate below.
            'validAccessors'        => []
        ];

        //override the visibility setting for generated id fields, which can't have setters.
        if(isset($mapping['id']) && $mapping['id'] &&
           $metadata->generatorType != ClassMetadataInfo::GENERATOR_TYPE_NONE &&
           $settings['visibility']!=$annotationClass::VISIBILITY_PRIVATE)
        {
            $settings['visibility'] = $annotationClass::VISIBILITY_READONLY;
        }

        //map visibility settings to the list of accessors to return
        $settings['validAccessors'] = $this->getAccessorsForVisibility($settings['visibility'], $storesCollection);

        /**
         * We don't want to duplicate methods that we already added to trait associated with the parent
         * class this property came from, so if it wasn't locally defined (as we've been assuming), we
         * need to set the accessors that it's associated with back to an empty array, unless there's a
         * local annotation that specifies that we should treat this property differently than we did in
         * the parent class. So in this conditional, we unset the valid accessors, look for any local
         * annotations (which would be class level, as we don't have the property here) and then diff them
         * with the annotation pulled from where the property was defined, which is in $fieldAnnotation.
         */
        if(!$this->hasPropertyLocally($fieldName, $metadata->name))
        {
            $settings['validAccessors'] = array();

            //the load the local annotations, which we'll parse for overrides
            $localAnnotations = $this->reader->getClassLevelPropertyAnnotations($localReflClass, $fieldName, $annotationClass);
            $localAnnotation = new AccessorSettings([]);
            array_walk($localAnnotations, function($a) use ($localAnnotation) { $localAnnotation->mergeIn($a); });

            if(count($localAnnotations)>0)
            {
                $localVisibility = $getSetting('visibility', $localAnnotation, null, null, $settings['visibility']);

                //visibility can only be made more expansive, not more restrictive, as the parent classes will already
                //have those less-restrictive accesors. So if we were private in the declaring class, our visibility can be
                //set to anything here. And if we're public here, that's obviously more or equally expansive than/as any parent.
                if($settings['visibility']!==$localVisibility && ($settings['visibility']==$annotationClass::VISIBILITY_PRIVATE || $localVisibility==$annotationClass::VISIBILITY_PUBLIC))
                {
                    $settings['visibility'] = $localVisibility;
                    $settings['validAccessors'] = $this->getAccessorsForVisibility($localVisibility, $storesCollection);
                }

                //set the other annotation properties to the local values, falling back to the existing (i.e. parent) settings when there's no override.
                $settings['otherSideMethodEnding'] = $getSetting('otherSideMethodEnding', $localAnnotation, null, null, $settings['otherSideMethodEnding']);
                $settings['publicName']            = $getSetting('publicName', $localAnnotation, null, null, $settings['publicName']);
                $settings['singular']              = $getSetting('singular', $localAnnotation, null, null, $settings['singular']);
            }

        }

        return $settings;
    }

    /**
     * Check if a method already exists on this class or its parent, so we know not to rewrite it.
     *
     * Looks at Reflection and $staticReflection to make sure all methods are included.
     */
    protected function hasMethod($method, $FCQN)
    {
        return (method_exists($FCQN, $method) || (
            isset($this->staticReflection[$FCQN]) &&
                in_array($method, $this->staticReflection[$FCQN]['methods'])
        ));
    }

    protected function hasPropertyLocally($property, $FCQN)
    {
        try {
            $refl = new \ReflectionProperty($FCQN, $property);
        } //in case it doesn't have the property at all (comes up with mapped superclass handling)
        catch(\Exception $e) {
            return false;
        }

        return ($FCQN == $refl->getDeclaringClass()->getName());
    }

    protected function getAccessorsForVisibility($visibility, $storesCollection)
    {
        $annotationClass = $this->annotationClass;

        switch($visibility)
        {
            case $annotationClass::VISIBILITY_PRIVATE:
                return [];

            case $annotationClass::VISIBILITY_READONLY:
                return ['get'];

            case $annotationClass::VISIBILITY_WRITEONLY:
                return $storesCollection ? ['set', 'add','remove'] : ['set'];

            case $annotationClass::VISIBILITY_PUBLIC:
                return $storesCollection ? ['get','set','add','remove'] : ['get','set'];

            default:
                return [];
        }
    }

    /**
     * Looks through the metadata objects of all the classes we're generating accessors for, in order
     * to find fieldMappings that were originally defined in the mapped super-class provided.
     *
     * Doctrine stores a mapped-superclass's mappings on the child entities that are actually inheriting them,
     * rather than in the metadata for the mapped superclass itself, hence the need to look through other metadatas.
     */
    protected function getMappingsForMappedSuperClass(ClassMetadataInfo $metadata)
    {
        //find the child class
        $subclass = array_values(
            array_filter(
                $this->metadatas,
                function ($thisMetadata) use ($metadata) {
                    return is_subclass_of($thisMetadata->name, $metadata->name);
                }
            )
        )[0];

        //extract the properties that were actually defined on the parent
        if ($subclass) {
            $propertyFilter = function ($mapping) use ($metadata) {
                return $this->hasPropertyLocally($mapping['fieldName'], $metadata->name); };

            $fieldMappings = array_filter($subclass->fieldMappings, $propertyFilter);
            $associationMappings = array_filter($subclass->associationMappings, $propertyFilter);
            //@todo carry over some of the subclass metadata too so mapped super class's with id's don't end up with setId()s
            //which happens by default because their metadata acts as though they don't have any id fields (bc they're inherited).
        }

        //and throw an exception if no child class could be found.
        else {
            throw new \Exception("Couldn't generate trait for MappedSuperClass because no subclass could be found.");
        }

        return ['fieldMappings'=>$fieldMappings,'associationMappings'=>$associationMappings];
    }

    protected function getClassName($fullNameFromMetadata)
    {
        return ($pos = strrpos($fullNameFromMetadata, '\\'))
            ? substr($fullNameFromMetadata, $pos + 1, strlen($fullNameFromMetadata)) : $fullNameFromMetadata;
    }

    protected function getNamespace(ClassMetadataInfo $metadata)
    {
        return substr($metadata->name, 0, strrpos($metadata->name, '\\'));
    }

    protected function isAssociationIsNullable($associationMapping)
    {
        if (isset($associationMapping['id']) && $associationMapping['id']) {
            return false;
        }
        if (isset($associationMapping['joinColumns'])) {
            $joinColumns = $associationMapping['joinColumns'];
        } else {
            //@todo there is no way to retrieve targetEntity metadata
            $joinColumns = array();
        }
        foreach ($joinColumns as $joinColumn) {
            if (isset($joinColumn['nullable']) && !$joinColumn['nullable']) {
                return false;
            }
        }

        return true;
    }

    public function updateSettings(array $settings)
    {
        foreach(['backupExisting', 'extension'] as $genericSetting) {

            if(isset($settings[$genericSetting])) {
                $this->{$genericSetting} = $settings[$genericSetting];
            }
        }

        if(isset($settings['numSpaces'])) {
            $this->spaces = str_repeat(' ', (int) $settings['numSpaces']);
        }
    }

    protected function getType($type)
    {
        if (isset($this->typeAlias[$type])) {
            return $this->typeAlias[$type];
        }

        return $type;
    }

    protected function prefixCodeWithSpaces($code, $num = 1)
    {
        $lines = explode("\n", $code);

        foreach ($lines as &$line) {
            $line = str_repeat($this->spaces, $num) . $line;
        }

        return implode("\n", $lines);
    }

    /**
     * Does static analysis on the provided source to popluate $this->staticReflection with
     * the names of the properties and methods that the class in the provided source contains.
     *
     * @todo this won't work if there is a namespace in brackets and a class outside of it.
     * @param string $src
     */
    protected function parseTokensInEntityFile($src)
    {
        $tokens = token_get_all($src);
        $lastSeenNamespace = "";
        $lastSeenClass = false;

        $inNamespace = false;
        $inClass = false;
        for ($i = 0; $i < count($tokens); $i++) {
            $token = $tokens[$i];
            if (in_array($token[0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT))) {
                continue;
            }

            if ($inNamespace) {
                if ($token[0] == T_NS_SEPARATOR || $token[0] == T_STRING) {
                    $lastSeenNamespace .= $token[1];
                } else {
                    if (is_string($token) && in_array($token, array(';', '{'))) {
                        $inNamespace = false;
                    }
                }
            }

            if ($inClass) {
                $inClass = false;
                $lastSeenClass = $lastSeenNamespace . ($lastSeenNamespace ? '\\' : '') . $token[1];
                $this->staticReflection[$lastSeenClass]['properties'] = array();
                $this->staticReflection[$lastSeenClass]['methods'] = array();
            }

            if ($token[0] == T_NAMESPACE) {
                $lastSeenNamespace = "";
                $inNamespace = true;
            } else if ($token[0] == T_CLASS) {
                $inClass = true;
            } else if ($token[0] == T_FUNCTION) {
                if ($tokens[$i + 2][0] == T_STRING) {
                    $this->staticReflection[$lastSeenClass]['methods'][] = $tokens[$i + 2][1];
                } else if ($tokens[$i + 2] == "&" && $tokens[$i + 3][0] == T_STRING) {
                    $this->staticReflection[$lastSeenClass]['methods'][] = $tokens[$i + 3][1];
                }
            } else if (in_array(
                $token[0],
                array(T_VAR, T_PUBLIC, T_PRIVATE, T_PROTECTED)
            ) && $tokens[$i + 2][0] != T_FUNCTION
            ) {
                $this->staticReflection[$lastSeenClass]['properties'][] = substr($tokens[$i + 2][1], 1);
            }
        }
    }




    const PLURAL_SUFFIX = 0;

    const PLURAL_SUFFIX_LENGTH = 1;

    const PLURAL_SUFFIX_AFTER_VOCAL = 2;

    const PLURAL_SUFFIX_AFTER_CONS = 3;

    const SINGULAR_SUFFIX = 4;

    /**
     * Map english plural to singular suffixes
     *
     * @var array
     *
     * @see http://english-zone.com/spelling/plurals.html
     * @see http://www.scribd.com/doc/3271143/List-of-100-Irregular-Plural-Nouns-in-English
     */
    private static $pluralMap = array(
        // First entry: plural suffix, reversed
        // Second entry: length of plural suffix
        // Third entry: Whether the suffix may succeed a vocal
        // Fourth entry: Whether the suffix may succeed a consonant
        // Fifth entry: singular suffix, normal

        // bacteria (bacterium), criteria (criterion), phenomena (phenomenon)
        array('a', 1, true, true, array('on', 'um')),

        // nebulae (nebula)
        array('ea', 2, true, true, 'a'),

        // mice (mouse), lice (louse)
        array('eci', 3, false, true, 'ouse'),

        // geese (goose)
        array('esee', 4, false, true, 'oose'),

        // fungi (fungus), alumni (alumnus), syllabi (syllabus), radii (radius)
        array('i', 1, true, true, 'us'),

        // men (man), women (woman)
        array('nem', 3, true, true, 'man'),

        // children (child)
        array('nerdlihc', 8, true, true, 'child'),

        // oxen (ox)
        array('nexo', 4, false, false, 'ox'),

        // indices (index), appendices (appendix)
        array('seci', 4, false, true, array('ex', 'ix')),

        // babies (baby)
        array('sei', 3, false, true, 'y'),

        // analyses (analysis), ellipses (ellipsis), funguses (fungus),
        // neuroses (neurosis), theses (thesis), emphases (emphasis),
        // oases (oasis), crises (crisis), houses (house), bases (base),
        // atlases (atlas), kisses (kiss)
        array('ses', 3, true, true, array('s', 'se', 'sis')),

        // lives (life), wives (wife)
        array('sevi', 4, false, true, 'ife'),

        // hooves (hoof), dwarves (dwarf), elves (elf), leaves (leaf)
        array('sev', 3, true, true, 'f'),

        // axes (axis), axes (ax), axes (axe)
        array('sexa', 4, false, false, array('ax', 'axe', 'axis')),

        // indexes (index), matrixes (matrix)
        array('sex', 3, true, false, 'x'),

        // quizzes (quiz)
        array('sezz', 4, true, false, 'z'),

        // bureaus (bureau)
        array('suae', 4, false, true, 'eau'),

        // roses (rose), garages (garage), cassettes (cassette),
        // waltzes (waltz), heroes (hero), bushes (bush), arches (arch),
        // shoes (shoe)
        array('se', 2, true, true, array('', 'e')),

        // tags (tag)
        array('s', 1, true, true, ''),

        // chateaux (chateau)
        array('xuae', 4, false, true, 'eau'),
    );

    /**
     * Returns the singular form of a word
     *
     * If the method can't determine the form with certainty, an array of the
     * possible singulars is returned.
     *
     * @param string $plural A word in plural form
     * @return string|array The singular form or an array of possible singular
     *                      forms
     */
    public static function singularify($plural)
    {
        $pluralRev = strrev($plural);
        $lowerPluralRev = strtolower($pluralRev);
        $pluralLength = strlen($lowerPluralRev);

        // The outer loop $i iterates over the entries of the plural table
        // The inner loop $j iterates over the characters of the plural suffix
        // in the plural table to compare them with the characters of the actual
        // given plural suffix
        for ($i = 0, $numPlurals = count(self::$pluralMap); $i < $numPlurals; ++$i) {
            $suffix = self::$pluralMap[$i][self::PLURAL_SUFFIX];
            $suffixLength = self::$pluralMap[$i][self::PLURAL_SUFFIX_LENGTH];
            $j = 0;

            // Compare characters in the plural table and of the suffix of the
            // given plural one by one
            while ($suffix[$j] === $lowerPluralRev[$j]) {
                // Let $j point to the next character
                ++$j;

                // Successfully compared the last character
                // Add an entry with the singular suffix to the singular array
                if ($j === $suffixLength) {
                    // Is there any character preceding the suffix in the plural string?
                    if ($j < $pluralLength) {
                        $nextIsVocal = false !== strpos('aeiou', $lowerPluralRev[$j]);

                        if (!self::$pluralMap[$i][self::PLURAL_SUFFIX_AFTER_VOCAL] && $nextIsVocal) {
                            break;
                        }

                        if (!self::$pluralMap[$i][self::PLURAL_SUFFIX_AFTER_CONS] && !$nextIsVocal) {
                            break;
                        }
                    }

                    $newBase = substr($plural, 0, $pluralLength - $suffixLength);
                    $newSuffix = self::$pluralMap[$i][self::SINGULAR_SUFFIX];

                    // Check whether the first character in the plural suffix
                    // is uppercased. If yes, uppercase the first character in
                    // the singular suffix too
                    $firstUpper = ctype_upper($pluralRev[$j - 1]);

                    if (is_array($newSuffix)) {
                        $singulars = array();

                        foreach ($newSuffix as $newSuffixEntry) {
                            $singulars[] = $newBase . ($firstUpper ? ucfirst($newSuffixEntry) : $newSuffixEntry);
                        }

                        return $singulars;
                    }

                    return $newBase . ($firstUpper ? ucFirst($newSuffix) : $newSuffix);
                }

                // Suffix is longer than word
                if ($j === $pluralLength) {
                    break;
                }
            }
        }

        // Convert teeth to tooth, feet to foot
        if (false !== ($pos = strpos($plural, 'ee'))) {
            return substr_replace($plural, 'oo', $pos, 2);
        }

        // Assume that plural and singular is identical
        return $plural;
    }
}